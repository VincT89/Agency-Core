<?php

namespace Tests\Feature;

use App\Domain\Shooting\Actions\ClientConfirmAction;
use App\Domain\Shooting\Actions\CreateShootRequestAction;
use App\Domain\Shooting\Actions\MarkClientInformedAction;
use App\Domain\Shooting\Actions\PhotographerRespondAction;
use App\Domain\Shooting\Actions\ReopenShootProposalAction;
use App\Domain\Shooting\Services\ShootingClientCommunicationService;
use App\Domain\Dashboard\Queries\PhotographerDashboardQuery;
use App\Enums\Shooting\ShootClientContactChannel;
use App\Enums\Shooting\ShootingWorkflowEvent;
use App\Enums\Shooting\ShootSlotPeriod;
use App\Enums\Shooting\ShootStatus;
use App\Livewire\Admin\Shooting\ShootShow;
use App\Livewire\Notifications\NotificationDropdown;
use App\Livewire\Photography\Shooting\MyShootShow;
use App\Livewire\Social\Shooting\CreateRequest;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\Project;
use App\Models\Shooting\Shoot;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ShootingWorkflowNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ShootingWorkflowCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $marketing;

    private User $photographer;

    private User $admin;

    private Client $client;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->marketing = User::factory()->create(['role' => 'marketing']);
        $this->photographer = User::factory()->create(['role' => 'photographer']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->client = Client::factory()->create([
            'name' => 'Cliente Shooting',
            'reference_person' => 'Anna Cliente',
            'email' => 'cliente@example.test',
            'phone' => '+39 333 1234567',
            'normalized_phone' => '393331234567',
        ]);
        $this->project = Project::factory()->create(['client_id' => $this->client->id]);
        $this->project->users()->attach($this->marketing->id, ['role' => 'owner']);
    }

    public function test_marketing_creates_request_and_photographer_is_notified(): void
    {
        $this->actingAs($this->marketing);

        $shoot = app(CreateShootRequestAction::class)->execute([
            'project_id' => $this->project->id,
            'photographer_id' => $this->photographer->id,
            'title' => 'Nuova collezione',
            'location' => 'Showroom cliente',
            'client_notes' => 'Preparare i prodotti principali.',
            'slots' => [[
                'date' => now()->addWeek()->toDateString(),
                'period' => ShootSlotPeriod::Morning->value,
            ]],
        ], $this->marketing->id);

        $this->assertSame(ShootStatus::WaitingPhotographer, $shoot->status);
        $this->assertSame($this->photographer->id, $shoot->photographer_id);
        $this->assertCount(1, $shoot->slots);

        Notification::assertSentTo(
            $this->photographer,
            ShootingWorkflowNotification::class,
            fn (ShootingWorkflowNotification $notification) =>
                $notification->event === ShootingWorkflowEvent::RequestCreated
                && $notification->shootId === $shoot->id
        );
    }

    public function test_shooting_notification_uses_direct_role_route_and_camera_icon(): void
    {
        $shoot = $this->createShoot();
        $notification = new ShootingWorkflowNotification(
            ShootingWorkflowEvent::RequestCreated,
            'Nuova richiesta shooting',
            'Controlla le date proposte.',
            url('/shoots/'.$shoot->id),
            $shoot->id
        );

        $payload = $notification->toArray($this->photographer);

        $this->assertSame('shooting', $payload['category']);
        $this->assertSame(
            route('photography.shooting.show', $shoot),
            $payload['url']
        );
        $this->assertSame($payload['url'], $payload['intended_url']);
        $this->assertArrayNotHasKey('intended_route', $payload);

        $storedNotification = $this->photographer->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => ShootingWorkflowNotification::class,
            'data' => $payload,
        ]);

        Livewire::actingAs($this->photographer)
            ->test(NotificationDropdown::class)
            ->assertSee('data-lucide="camera"', false)
            ->call('markAsReadAndRedirect', $storedNotification->id)
            ->assertRedirect(route('photography.shooting.show', $shoot));
    }

    public function test_photographer_confirmation_client_contact_and_marketing_confirmation_complete_flow(): void
    {
        $this->actingAs($this->marketing);
        $shoot = $this->createShoot();
        $slot = $shoot->slots->first();

        $this->actingAs($this->photographer);
        app(PhotographerRespondAction::class)->execute($shoot, $slot->id, 'Disponibile');

        $shoot->refresh();
        $this->assertSame(ShootStatus::WaitingClient, $shoot->status);
        $this->assertSame($slot->id, $shoot->selected_slot_id);

        Notification::assertSentTo(
            $this->marketing,
            ShootingWorkflowNotification::class,
            fn (ShootingWorkflowNotification $notification) =>
                $notification->event === ShootingWorkflowEvent::PhotographerAccepted
        );

        $this->actingAs($this->marketing);
        app(MarkClientInformedAction::class)->execute(
            $shoot,
            ShootClientContactChannel::Whatsapp
        );

        $shoot->refresh();
        $this->assertNotNull($shoot->client_notified_at);
        $this->assertSame('whatsapp', $shoot->client_confirmation_channel);
        $this->assertSame('393331234567', $shoot->client_notification_recipient);
        $this->assertTrue($this->marketing->can('confirmClient', $shoot));

        app(ClientConfirmAction::class)->execute($shoot, true, $this->marketing->id);

        $shoot->refresh();
        $this->assertSame(ShootStatus::Scheduled, $shoot->status);
        $this->assertSame('accepted', $shoot->client_confirmation_status);
        $this->assertNotNull($shoot->calendar_event_id);
        $this->assertNotNull($shoot->task_id);
        $this->assertDatabaseHas(CalendarEvent::class, [
            'id' => $shoot->calendar_event_id,
            'client_id' => $this->client->id,
            'assigned_to' => $this->photographer->id,
        ]);
        $this->assertDatabaseHas(Task::class, [
            'id' => $shoot->task_id,
            'assigned_to' => $this->photographer->id,
            'status' => 'todo',
        ]);
    }

    public function test_client_response_cannot_be_recorded_before_contact(): void
    {
        $shoot = $this->createShoot();
        $slot = $shoot->slots->first();
        app(PhotographerRespondAction::class)->execute($shoot, $slot->id);
        $shoot->refresh();

        $this->expectException(ValidationException::class);

        app(ClientConfirmAction::class)->execute($shoot, true, $this->marketing->id);
    }

    public function test_rejected_date_can_be_reopened_with_new_photographer_and_slots(): void
    {
        $shoot = $this->createShoot();
        $oldSlot = $shoot->slots->first();
        app(PhotographerRespondAction::class)->execute($shoot, $oldSlot->id);
        $shoot->refresh();
        app(MarkClientInformedAction::class)->execute(
            $shoot,
            ShootClientContactChannel::Phone
        );
        $shoot->refresh();
        app(ClientConfirmAction::class)->execute($shoot, false, $this->marketing->id);

        $newPhotographer = User::factory()->create(['role' => 'photographer']);
        $newDate = now()->addWeeks(2)->toDateString();

        app(ReopenShootProposalAction::class)->execute(
            $shoot->refresh(),
            $newPhotographer->id,
            [[
                'date' => $newDate,
                'period' => ShootSlotPeriod::Afternoon->value,
            ]]
        );

        $shoot->refresh();
        $this->assertSame(ShootStatus::WaitingPhotographer, $shoot->status);
        $this->assertSame($newPhotographer->id, $shoot->photographer_id);
        $this->assertNull($shoot->selected_slot_id);
        $this->assertNull($shoot->client_notified_at);
        $this->assertNull($shoot->client_confirmation_channel);
        $this->assertSoftDeleted('shoot_slots', ['id' => $oldSlot->id]);
        $this->assertSame(
            $newDate,
            $shoot->slots()->firstOrFail()->date->toDateString()
        );

        Notification::assertSentTo(
            $newPhotographer,
            ShootingWorkflowNotification::class,
            fn (ShootingWorkflowNotification $notification) =>
                $notification->event === ShootingWorkflowEvent::RequestReopened
        );
    }

    public function test_campaign_only_request_renders_for_photographer_and_admin(): void
    {
        $campaign = MarketingCampaign::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Campagna senza progetto',
        ]);

        $shoot = Shoot::factory()->create([
            'project_id' => null,
            'marketing_campaign_id' => $campaign->id,
            'photographer_id' => $this->photographer->id,
            'created_by' => $this->marketing->id,
            'status' => ShootStatus::WaitingPhotographer,
        ]);

        Livewire::actingAs($this->photographer)
            ->test(MyShootShow::class, ['shoot' => $shoot])
            ->assertSee('Campagna senza progetto');

        Livewire::actingAs($this->admin)
            ->test(ShootShow::class, ['shoot' => $shoot])
            ->assertSee('Campagna senza progetto');
    }

    public function test_marketing_cannot_see_the_shooting_audit_history(): void
    {
        $shoot = $this->createShoot();

        Livewire::actingAs($this->marketing)
            ->test(\App\Livewire\Social\Shooting\RequestShow::class, ['shoot' => $shoot])
            ->assertDontSee('Storico attività');
    }

    public function test_assigned_photographer_sees_the_project_context_without_project_membership(): void
    {
        $shoot = Shoot::factory()->create([
            'project_id' => $this->project->id,
            'marketing_campaign_id' => null,
            'photographer_id' => $this->photographer->id,
            'created_by' => $this->marketing->id,
            'status' => ShootStatus::WaitingPhotographer,
        ]);

        Livewire::actingAs($this->photographer)
            ->test(MyShootShow::class, ['shoot' => $shoot])
            ->assertSee($this->project->name);

        $dashboard = app(PhotographerDashboardQuery::class)
            ->getDashboardData($this->photographer);

        $this->assertSame(
            $this->project->name,
            $dashboard->queue_da_rispondere[0]->project_name
        );
    }

    public function test_photographer_is_required_and_developer_cannot_create_requests(): void
    {
        Livewire::actingAs($this->marketing)
            ->test(CreateRequest::class)
            ->set('project_id', $this->project->id)
            ->set('proposedSlots', [[
                'date' => now()->addWeek()->toDateString(),
                'period' => ShootSlotPeriod::Morning->value,
            ]])
            ->call('save')
            ->assertHasErrors(['photographer_id']);

        $developer = User::factory()->create(['role' => 'developer']);
        $this->assertFalse($developer->can('create', Shoot::class));
        $this->assertTrue($this->marketing->can('create', Shoot::class));
    }

    public function test_photographer_cannot_accept_a_slot_from_another_shoot(): void
    {
        $shoot = $this->createShoot();
        $otherShoot = $this->createShoot();
        $foreignSlot = $otherShoot->slots->first();

        $this->expectException(ValidationException::class);

        app(PhotographerRespondAction::class)->execute($shoot, $foreignSlot->id);
    }

    public function test_whatsapp_link_uses_the_source_phone_without_duplicating_the_country_code(): void
    {
        $this->client->updateQuietly([
            'phone' => '+39 06 987654',
            'normalized_phone' => '393906987654',
        ]);

        $shoot = $this->createShoot();
        $communication = app(ShootingClientCommunicationService::class)->for($shoot);

        $this->assertStringStartsWith(
            'https://wa.me/3906987654?',
            $communication['whatsapp_url']
        );
    }

    private function createShoot(): Shoot
    {
        $this->actingAs($this->marketing);

        return app(CreateShootRequestAction::class)->execute([
            'project_id' => $this->project->id,
            'photographer_id' => $this->photographer->id,
            'title' => 'Shooting cliente',
            'location' => 'Sede cliente',
            'client_notes' => 'Portare tre prodotti.',
            'internal_notes' => 'Usare luce naturale.',
            'slots' => [[
                'date' => now()->addWeek()->toDateString(),
                'period' => ShootSlotPeriod::Morning->value,
            ]],
        ], $this->marketing->id);
    }
}
