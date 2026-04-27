<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Shooting\Shoot;
use App\Models\Shooting\ShootSlot;
use Livewire\Livewire;
use App\Livewire\Photography\Shooting\MyShootShow;
use App\Livewire\Admin\Shooting\ShootShow;
use App\Enums\Shooting\ShootStatus;
use App\Enums\Shooting\ShootSlotStatus;

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

        $project = \App\Models\Project::factory()->create();
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
            'period' => \App\Enums\Shooting\ShootSlotPeriod::Morning,
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
            'selected_slot_id' => $this->slot->id
        ]);
        
        Livewire::actingAs($this->admin)
            ->test(ShootShow::class, ['shoot' => $this->shoot])
            ->call('confirmForClient')
            ->assertHasNoErrors();
            
        $this->assertEquals(ShootStatus::Scheduled, $this->shoot->fresh()->status);
    }
}
