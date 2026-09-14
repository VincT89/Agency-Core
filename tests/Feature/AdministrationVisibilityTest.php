<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\Social\AgencySocialConnections;
use App\Livewire\Admin\Social\SocialOperationsDashboard;
use App\Livewire\Client\ClientSocialAccountForm;
use App\Livewire\Social\MarketingCampaigns\MarketingCampaignPostShow;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignPost;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdministrationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_administration_has_management_views_without_system_access_or_new_mutation_rights(): void
    {
        Bus::fake();
        Notification::fake();
        Http::preventStrayRequests();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active', 'password_changed_at' => now()]);
        $user = User::factory()->create(['role' => UserRole::Administration, 'status' => 'active', 'password_changed_at' => now()]);
        $this->actingAs($admin);
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $post = MarketingCampaignPost::factory()->create(['marketing_campaign_id' => $campaign->id, 'content_type' => 'post', 'title' => 'Post dimostrativo consultabile']);
        $client->socialAccounts()->create(['platform' => 'facebook', 'account_name' => 'Profilo dimostrativo', 'connection_strategy' => 'manual_token_config', 'access_status' => 'not_started']);
        $this->actingAs($user);
        $this->get(route('dashboard'))->assertOk()->assertSee('Progetti Marketing')->assertSee('Coda Social')->assertDontSee('Attività Recenti')->assertDontSee('Disponibilità team')->assertDontSee('Registro attività');
        foreach (['tasks.index', 'tickets.index', 'calendar-events.index', 'clients.index', 'projects.index', 'teams.index', 'marketing-campaigns.index', 'social.calendar', 'admin.shooting.index', 'admin.social.connections.index', 'admin.social.operations.index', 'invoices.index', 'payments.index', 'hosting-services.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('marketing-campaigns.show', $campaign))->assertOk()->assertSee('Contratto / Periodi')->assertDontSee('wire:click="openExtraModal"', false);
        $this->get(route('clients.show', $client))->assertOk()->assertSee('Accessi Social');
        $this->get(route('marketing-campaigns.posts.show', [$campaign, $post]))->assertOk()->assertSee($post->title)->assertSee('permission-fieldset form-stack" disabled', false);
        foreach (['users.index', 'users.create', 'admin.availability.index', 'audit-logs.index', 'tasks.create', 'tickets.create', 'calendar-events.create', 'marketing-campaigns.create', 'admin.social.connections.meta.redirect'] as $route) {
            $response = $this->get(route($route));
            $this->assertSame(403, $response->getStatusCode(), $route);
        }
        $this->assertFalse(Gate::allows('system.admin'));
        $this->assertFalse(Gate::allows('manage_social_connections'));
        $this->assertFalse(Gate::allows('manage_social_operations'));
        $this->assertFalse(Gate::allows('update', $client));
        $this->assertFalse(Gate::allows('delete', $project));
        $this->assertFalse(Gate::allows('create', Task::class));
        $this->assertFalse(Gate::allows('create', Ticket::class));
        $this->assertFalse(Gate::allows('update', $campaign));
        $this->assertTrue(Gate::allows('create', Quote::class));
        Livewire::test(AgencySocialConnections::class)->call('syncConnection', 1)->assertForbidden();
        Livewire::test(AgencySocialConnections::class)->call('revokeConnection', 1)->assertForbidden();
        Livewire::test(SocialOperationsDashboard::class)->call('forceFailPublication', 1)->assertForbidden();
        Livewire::test(ClientSocialAccountForm::class, ['client' => $client])->call('save', 'facebook')->assertForbidden();
        Livewire::test(ClientSocialAccountForm::class, ['client' => $client])->call('disconnect', 'facebook')->assertForbidden();
        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])->call('savePost')->assertForbidden();
        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])->call('refreshPreflight')->assertForbidden();
        Livewire::test(MarketingCampaignPostShow::class, ['campaign' => $campaign, 'post' => $post])->call('browseNextcloud')->assertForbidden();
        Http::assertNothingSent();
    }
}
