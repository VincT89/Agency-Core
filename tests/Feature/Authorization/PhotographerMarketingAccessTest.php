<?php

namespace Tests\Feature\Authorization;

use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\UserRole;
use App\Helpers\ShootingRouteResolver;
use App\Livewire\Admin\Social\SocialOperationsDashboard;
use App\Livewire\Photography\Shooting\MyShootShow;
use App\Livewire\Photography\Shooting\MyShootsIndex;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignCreate;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostCreate;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow;
use App\Livewire\Social\Shooting\CreateRequest;
use App\Livewire\Social\Shooting\RequestShow;
use App\Models\Chatbot\ChatbotClient;
use App\Models\Chatbot\ChatbotMarketingPost;
use App\Models\Client;
use App\Models\ClientSocialAccount;
use App\Models\Invoice;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostPublication;
use App\Models\Project;
use App\Models\Shooting\Shoot;
use App\Models\User;
use App\Notifications\ChatbotClientInteractionNotification;
use App\Notifications\ShootingWorkflowNotification;
use App\Services\Integrations\Nextcloud\NextcloudPathAuthorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class PhotographerMarketingAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Queue::fake();
        Notification::fake();
    }

    public function test_marketing_permissions_are_shared_without_changing_role_identity(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $canManage = in_array($role, [UserRole::Admin, UserRole::Marketing, UserRole::Photographer], true);
            $canView = $canManage || $role === UserRole::Administration;

            $this->assertSame($canManage, $user->can('create', MarketingCampaign::class), $role->value);
            $this->assertSame($canManage, $user->can('create', MarketingCampaignPost::class), $role->value);
            $this->assertSame($canManage, $user->can('create', ClientSocialAccount::class), $role->value);
            $this->assertSame($canManage, $user->can('manage_social_operations'), $role->value);
            $this->assertSame($canView, $user->can('view_social_operations'), $role->value);
            $this->assertSame($role === UserRole::Marketing, $user->isMarketing());
            $this->assertSame($role === UserRole::Photographer, $user->isPhotographer());
        }
    }

    public function test_photographer_can_create_campaign_and_save_and_edit_its_post(): void
    {
        $photographer = User::factory()->create(['role' => UserRole::Photographer]);
        $client = Client::factory()->create(['status' => 'active']);
        $this->actingAs($photographer);

        $this->getJson(route('api.clients.search', ['q' => $client->name]))
            ->assertOk()->assertJsonFragment(['id' => $client->id]);

        Livewire::test(MarketingCampaignCreate::class)
            ->set('client_id', $client->id)
            ->set('name', 'Campagna del fotografo')
            ->call('save')->assertHasNoErrors();

        $campaign = MarketingCampaign::where('name', 'Campagna del fotografo')->sole();
        $this->assertSame($photographer->id, $campaign->created_by);
        $this->assertTrue(Client::visibleTo($photographer)->whereKey($client->id)->exists());

        Livewire::test(MarketingCampaignPostCreate::class, ['campaign' => $campaign])
            ->set('form.title', 'Bozza del fotografo')
            ->call('save')->assertHasNoErrors();

        $post = $campaign->posts()->sole();
        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])
            ->set('form.title', 'Titolo aggiornato dal fotografo')
            ->call('saveDraft')->assertHasNoErrors();

        $this->assertSame('Titolo aggiornato dal fotografo', $post->fresh()->title);
        $this->assertSame(MarketingCampaignPostStatus::Draft, $post->fresh()->status);
    }

    public function test_marketing_pages_and_dashboard_are_available_without_project_membership(): void
    {
        $photographer = User::factory()->create(['role' => UserRole::Photographer]);
        $campaign = MarketingCampaign::factory()->create();
        $this->actingAs($photographer);

        $dashboard = $this->get(route('dashboard'))->assertOk();
        foreach (['Progetti Marketing', 'Calendario Campagne', 'Richieste Shooting', 'I Miei Shooting', 'Coda Social', 'Prossimi Eventi', 'Le Mie Richieste Shooting'] as $label) {
            $dashboard->assertSee($label);
        }
        $dashboard->assertDontSee('Connessioni Social');

        foreach (['marketing-campaigns.index', 'marketing-campaigns.create', 'social.calendar', 'social.shooting.index', 'social.shooting.create', 'photography.shooting.index', 'admin.social.operations.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('marketing-campaigns.show', $campaign))->assertOk()->assertSee($campaign->name);
        $this->get(route('marketing-campaigns.posts.create', $campaign))->assertOk();
    }

    public function test_shoot_request_created_by_photographer_is_accessible_and_notifies_assignee(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Photographer]);
        $assignee = User::factory()->create(['role' => UserRole::Photographer]);
        $campaign = MarketingCampaign::factory()->create();
        $this->actingAs($creator);

        Livewire::test(CreateRequest::class)
            ->set('title', 'Richiesta da fotografo a collega')
            ->set('marketing_campaign_id', $campaign->id)
            ->set('photographer_id', $assignee->id)
            ->set('proposedSlots', [['date' => now()->addWeek()->toDateString(), 'period' => 'morning']])
            ->call('save')->assertHasNoErrors();

        $shoot = Shoot::where('title', 'Richiesta da fotografo a collega')->sole();
        Notification::assertSentTo($assignee, ShootingWorkflowNotification::class);
        $this->get(route('social.shooting.show', $shoot))->assertOk();
        $this->assertSame(route('social.shooting.show', $shoot), ShootingRouteResolver::showRouteFor($creator, $shoot));
        $this->assertSame(route('photography.shooting.show', $shoot), ShootingRouteResolver::showRouteFor($assignee, $shoot));
        $this->assertFalse($creator->can('respond', $shoot));

        $this->actingAs(User::factory()->create(['role' => UserRole::Marketing]));
        $this->get(route('social.shooting.show', $shoot))->assertOk();
    }

    public function test_personal_shoot_list_and_response_remain_limited_to_assignee(): void
    {
        $photographer = User::factory()->create(['role' => UserRole::Photographer]);
        $campaign = MarketingCampaign::factory()->create();
        $own = Shoot::factory()->create(['project_id' => null, 'marketing_campaign_id' => $campaign->id, 'photographer_id' => $photographer->id, 'title' => 'Incarico personale']);
        $other = Shoot::factory()->create(['project_id' => null, 'marketing_campaign_id' => $campaign->id, 'title' => 'Incarico di un collega']);
        $this->actingAs($photographer);

        Livewire::test(MyShootsIndex::class)->assertSee($own->title)->assertDontSee($other->title);
        Livewire::test(MyShootShow::class, ['shoot' => $other])->assertForbidden();
        Livewire::test(RequestShow::class, ['shoot' => $other])->assertOk();
        $this->assertTrue($photographer->can('respond', $own));
        $this->assertFalse($photographer->can('respond', $other));
        $this->assertTrue($photographer->can('confirmClient', $other));
        $this->assertTrue($photographer->can('revise', $other));
        $this->assertFalse($photographer->can('delete', $other));
    }

    public function test_project_scope_is_preserved_except_for_directly_assigned_shoots(): void
    {
        $photographer = User::factory()->create(['role' => UserRole::Photographer]);
        $project = Project::factory()->create();
        $otherShoot = Shoot::factory()->create(['project_id' => $project->id]);
        $ownShoot = Shoot::factory()->create(['project_id' => $project->id, 'photographer_id' => $photographer->id]);
        $this->actingAs($photographer);

        $this->assertFalse(Project::visibleTo($photographer)->whereKey($project->id)->exists());
        $this->assertFalse(Shoot::whereKey($otherShoot->id)->exists());
        $this->assertFalse($photographer->can('view', $otherShoot));
        $this->get(route('photography.shooting.show', $ownShoot))->assertOk();

        $project->users()->attach($photographer->id, ['role' => 'contributor']);
        $this->assertTrue(Shoot::whereKey($otherShoot->id)->exists());
        $this->assertTrue($photographer->can('view', $otherShoot));
    }

    public function test_marketing_access_does_not_grant_finance_or_system_administration(): void
    {
        $photographer = User::factory()->create(['role' => UserRole::Photographer]);
        $this->actingAs($photographer);

        $this->assertSame('Fotografo', $photographer->role->label());
        $this->assertFalse(Gate::allows('system.admin'));
        $this->assertFalse(Gate::allows('view_social_connections'));
        $this->assertFalse(Gate::allows('manage_social_connections'));
        $this->assertFalse($photographer->can('viewAny', Invoice::class));
        $this->get(route('invoices.index'))->assertForbidden();
        $this->get(route('expenses.index'))->assertForbidden();
        $this->get(route('admin.social.connections.index'))->assertForbidden();
        $this->get(route('admin.availability.index'))->assertForbidden();
    }

    public function test_campaign_media_access_preserves_configured_root_boundaries(): void
    {
        $photographer = User::factory()->create(['role' => UserRole::Photographer]);
        $this->actingAs($photographer);
        config(['services.nextcloud.photos_root' => '/FotoClienti', 'services.nextcloud.videos_root' => '/VideoClienti']);
        $authorizer = app(NextcloudPathAuthorizer::class);

        $this->assertTrue($authorizer->canAccess($photographer, '/FotoClienti/Cliente senza commessa/post.jpg'));
        $this->assertTrue($authorizer->canAccess($photographer, '/VideoClienti/Cliente senza commessa/reel.mp4'));
        $this->assertFalse($authorizer->canAccess($photographer, '/DocumentiRiservati/file.jpg'));
        $this->assertFalse($authorizer->canAccess($photographer, '/FotoClientiPrivati/file.jpg'));
        $this->get('/nextcloud/download?path=../etc/passwd')->assertStatus(400);
    }

    public function test_photographer_can_manage_the_social_queue(): void
    {
        $photographer = User::factory()->create(['role' => UserRole::Photographer]);
        $post = MarketingCampaignPost::factory()->create(['status' => MarketingCampaignPostStatus::Failed]);
        $publication = MarketingCampaignPostPublication::create([
            'marketing_campaign_post_id' => $post->id,
            'platform' => 'facebook', 'status' => 'failed', 'correlation_id' => 'photographer-access-test',
        ]);

        $component = Livewire::actingAs($photographer)->test(SocialOperationsDashboard::class)
            ->call('archivePost', $publication->id)->assertHasNoErrors();
        $this->assertTrue($post->fresh()->isArchived());
        $this->assertSame($photographer->id, $post->fresh()->archived_by);
        $component->call('restorePost', $publication->id)->assertHasNoErrors();
        $this->assertFalse($post->fresh()->isArchived());
    }

    public function test_client_response_notifies_photographers_as_well_as_marketing(): void
    {
        config(['services.n8n.token' => 'photographer-test-token']);
        $photographer = User::factory()->create(['role' => UserRole::Photographer]);
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);
        $developer = User::factory()->create(['role' => UserRole::Developer]);
        $campaign = MarketingCampaign::factory()->create();
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id]);
        $chatbotClient = ChatbotClient::create(['client_id' => $campaign->client_id, 'name' => $campaign->client->name]);
        ChatbotMarketingPost::create([
            'chatbot_client_id' => $chatbotClient->id, 'client_id' => $campaign->client_id,
            'marketing_campaign_id' => $campaign->id, 'marketing_campaign_post_id' => $post->id, 'status' => 'sent_to_client',
        ]);

        $this->postJson('/api/v1/integrations/n8n/chatbot/client-message', [
            'client_id' => $campaign->client_id, 'session_type' => 'marketing', 'session_id' => $post->id,
            'message' => 'Approvato dal cliente', 'type' => 'approval',
        ], ['Authorization' => 'Bearer photographer-test-token'])->assertOk();

        Notification::assertSentTo([$photographer, $marketing], ChatbotClientInteractionNotification::class);
        Notification::assertNotSentTo($developer, ChatbotClientInteractionNotification::class);
        $this->assertSame(MarketingCampaignPostStatus::ClientApproved, $post->fresh()->status);
    }
}
