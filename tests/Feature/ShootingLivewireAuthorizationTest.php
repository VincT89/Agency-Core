<?php

namespace Tests\Feature;

use App\Enums\Shooting\ShootSlotPeriod;
use App\Enums\Shooting\ShootSlotStatus;
use App\Enums\Shooting\ShootStatus;
use App\Livewire\Admin\Shooting\ShootShow;
use App\Livewire\Photography\Shooting\MyShootShow;
use App\Livewire\Social\Shooting\CreateRequest;
use App\Models\Client;
use App\Models\MarketingCampaign;
use App\Models\Project;
use App\Models\Shooting\Shoot;
use App\Models\Shooting\ShootSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShootingLivewireAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $photographer1;

    private User $photographer2;

    private Shoot $shoot;

    private ShootSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->photographer1 = User::factory()->create(['role' => 'photographer']);
        $this->photographer2 = User::factory()->create(['role' => 'photographer']);

        $project = Project::factory()->create();
        $project->users()->attach([
            $this->admin->id => ['role' => 'owner'],
            $this->photographer1->id => ['role' => 'contributor'],
        ]);

        $this->shoot = Shoot::factory()->create([
            'photographer_id' => $this->photographer1->id,
            'status' => ShootStatus::WaitingPhotographer,
            'project_id' => $project->id,
        ]);

        $this->slot = ShootSlot::create([
            'shoot_id' => $this->shoot->id,
            'date' => now()->addDays(5),
            'period' => ShootSlotPeriod::Morning,
            'starts_at' => '09:00:00',
            'ends_at' => '13:00:00',
            'status' => ShootSlotStatus::Proposed,
        ]);
    }

    public function test_photographer_cannot_accept_slot_for_unassigned_shoot()
    {
        // Photographer2 tries to access Photographer1's shoot. The mount method uses authorize('view') which will abort 403.
        // If they bypass mount (e.g. state injection), acceptSlot will fail because authorize('respond') checks ownership.
        Livewire::actingAs($this->photographer2)
            ->test(MyShootShow::class, ['shoot' => $this->shoot])
            ->assertForbidden();

        // Let's test the specific method if view is somehow bypassed
        // actually test() runs mount, so it fails there.
    }

    public function test_photographer_can_accept_slot_for_assigned_shoot()
    {
        Livewire::actingAs($this->photographer1)
            ->test(MyShootShow::class, ['shoot' => $this->shoot])
            ->call('acceptSlot', $this->slot->id)
            ->assertHasNoErrors();

        $this->assertEquals(ShootStatus::WaitingClient, $this->shoot->fresh()->status);
    }

    public function test_admin_can_confirm_for_client()
    {
        $this->shoot->update([
            'status' => ShootStatus::WaitingClient,
            'selected_slot_id' => $this->slot->id,
            'client_notified_at' => now(),
            'client_confirmation_channel' => 'phone',
            'client_notification_recipient' => '+39 081 0000000',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ShootShow::class, ['shoot' => $this->shoot])
            ->call('confirmForClient')
            ->assertHasNoErrors();

        $this->assertEquals(ShootStatus::Scheduled, $this->shoot->fresh()->status);
    }

    public function test_developer_cannot_create_shoot_request(): void
    {
        $developer = User::factory()->create(['role' => 'developer']);

        Livewire::actingAs($developer)
            ->test(CreateRequest::class)
            ->assertForbidden();
    }

    public function test_project_and_campaign_must_belong_to_same_client(): void
    {
        $marketing = User::factory()->create(['role' => 'marketing']);
        $photographer = User::factory()->create(['role' => 'photographer']);
        $projectClient = Client::factory()->create();
        $campaignClient = Client::factory()->create();
        $selectedProject = Project::factory()->create(['client_id' => $projectClient->id]);
        $campaignAccessProject = Project::factory()->create(['client_id' => $campaignClient->id]);
        $selectedProject->users()->attach($marketing->id, ['role' => 'owner']);
        $campaignAccessProject->users()->attach($marketing->id, ['role' => 'owner']);
        $foreignCampaign = MarketingCampaign::factory()->create([
            'client_id' => $campaignClient->id,
        ]);
        $shootCount = Shoot::withoutGlobalScopes()->count();

        Livewire::actingAs($marketing)
            ->test(CreateRequest::class)
            ->set('project_id', $selectedProject->id)
            ->set('marketing_campaign_id', $foreignCampaign->id)
            ->set('photographer_id', $photographer->id)
            ->set('proposedSlots', [[
                'date' => now()->addWeek()->toDateString(),
                'period' => 'morning',
            ]])
            ->call('save')
            ->assertHasErrors(['marketing_campaign_id']);

        $this->assertSame($shootCount, Shoot::withoutGlobalScopes()->count());
    }

    public function test_developer_can_view_campaign_shoot_for_assigned_client(): void
    {
        $developer = User::factory()->create(['role' => 'developer']);
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $project->users()->attach($developer->id, ['role' => 'contributor']);
        $campaign = MarketingCampaign::factory()->create(['client_id' => $client->id]);
        $shoot = Shoot::factory()->create([
            'project_id' => null,
            'marketing_campaign_id' => $campaign->id,
        ]);

        $this->assertTrue($developer->can('view', $shoot));
    }
}
