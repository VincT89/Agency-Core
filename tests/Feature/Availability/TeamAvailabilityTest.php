<?php

namespace Tests\Feature\Availability;

use App\Enums\UserRole;
use App\Livewire\Admin\Availability\TeamAvailability;
use App\Models\User;
use App\Models\UserAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_users_availabilities(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $firstUser = User::factory()->create(['name' => 'Vincenzo']);
        $secondUser = User::factory()->create(['name' => 'Lucia']);

        UserAvailability::factory()->for($firstUser)->create([
            'date' => today()->toDateString(),
            'starts_at' => '08:00:00',
            'ends_at' => '14:00:00',
        ]);
        UserAvailability::factory()->for($secondUser)->create([
            'date' => today()->toDateString(),
            'starts_at' => '15:00:00',
            'ends_at' => '19:00:00',
        ]);

        Livewire::actingAs($admin)
            ->test(TeamAvailability::class)
            ->assertSee('Vincenzo')
            ->assertSee('08:00–14:00')
            ->assertSee('Lucia')
            ->assertSee('15:00–19:00');
    }

    public function test_non_admin_cannot_open_team_availability_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::Developer]);

        Livewire::actingAs($user)
            ->test(TeamAvailability::class)
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.availability.index'))
            ->assertForbidden();
    }

    public function test_admin_page_can_filter_the_week_by_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        UserAvailability::factory()->for($firstUser)->create([
            'date' => today()->toDateString(),
            'starts_at' => '08:00:00',
            'ends_at' => '14:00:00',
        ]);
        UserAvailability::factory()->for($secondUser)->create([
            'date' => today()->toDateString(),
            'starts_at' => '18:00:00',
            'ends_at' => '20:00:00',
        ]);

        Livewire::actingAs($admin)
            ->test(TeamAvailability::class)
            ->set('selectedUserId', (string) $firstUser->id)
            ->assertSee('08:00–14:00')
            ->assertDontSee('18:00–20:00');
    }

    public function test_admin_can_view_but_cannot_modify_another_users_availability(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create();
        $availability = UserAvailability::factory()->for($user)->create();

        $this->assertTrue($admin->can('view', $availability));
        $this->assertFalse($admin->can('update', $availability));
        $this->assertFalse($admin->can('delete', $availability));
    }

    public function test_personal_page_is_available_to_authenticated_users(): void
    {
        $user = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $this->actingAs($user)
            ->get(route('availability.index'))
            ->assertOk()
            ->assertSeeText('Le mie disponibilità');
    }
}
