<?php

namespace Tests\Feature\Availability;

use App\Enums\UserRole;
use App\Livewire\Availability\MyAvailability;
use App\Models\User;
use App\Models\UserAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::Developer,
            'status' => 'active',
        ]);
    }

    public function test_user_sees_only_their_own_availabilities(): void
    {
        $otherUser = User::factory()->create();

        UserAvailability::factory()->for($this->user)->create([
            'date' => today()->toDateString(),
            'starts_at' => '08:00:00',
            'ends_at' => '14:00:00',
        ]);
        UserAvailability::factory()->for($otherUser)->create([
            'date' => today()->toDateString(),
            'starts_at' => '18:00:00',
            'ends_at' => '20:00:00',
        ]);

        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->assertSee('08:00–14:00')
            ->assertDontSee('18:00–20:00');
    }

    public function test_editor_is_closed_until_the_user_selects_a_day(): void
    {
        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->assertSet('editorOpen', false)
            ->assertSet('editingId', null)
            ->assertDontSee('Nuova fascia');
    }

    public function test_selecting_a_day_opens_the_contextual_editor(): void
    {
        $date = today()->toDateString();

        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->call('beginCreate', $date)
            ->assertSet('editorOpen', true)
            ->assertSet('editingId', null)
            ->assertSet('date', $date)
            ->assertSet('startsAt', '08:00')
            ->assertSet('endsAt', '14:00')
            ->assertSee('Nuova fascia');
    }

    public function test_cancel_closes_and_resets_the_editor(): void
    {
        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->call('beginCreate', today()->toDateString())
            ->set('startsAt', '09:30')
            ->set('endsAt', '12:30')
            ->call('cancelEdit')
            ->assertSet('editorOpen', false)
            ->assertSet('editingId', null)
            ->assertSet('startsAt', '08:00')
            ->assertSet('endsAt', '14:00');
    }

    public function test_changing_week_closes_the_editor(): void
    {
        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->call('beginCreate', today()->toDateString())
            ->assertSet('editorOpen', true)
            ->call('nextWeek')
            ->assertSet('editorOpen', false)
            ->assertSet('editingId', null);
    }

    public function test_user_can_add_an_availability(): void
    {
        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->call('beginCreate', today()->toDateString())
            ->assertSet('editorOpen', true)
            ->set('startsAt', '08:00')
            ->set('endsAt', '14:00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editorOpen', false)
            ->assertSet('successMessage', 'Disponibilità aggiunta.');

        $this->assertDatabaseHas('user_availabilities', [
            'user_id' => $this->user->id,
            'starts_at' => '08:00:00',
            'ends_at' => '14:00:00',
        ]);

        $this->assertTrue(
            UserAvailability::query()->firstOrFail()->date->isToday()
        );
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->set('date', today()->toDateString())
            ->set('startsAt', '14:00')
            ->set('endsAt', '08:00')
            ->call('save')
            ->assertHasErrors(['endsAt']);

        $this->assertDatabaseCount('user_availabilities', 0);
    }

    public function test_past_dates_are_rejected(): void
    {
        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->set('date', today()->subDay()->toDateString())
            ->set('startsAt', '08:00')
            ->set('endsAt', '14:00')
            ->call('save')
            ->assertHasErrors(['date']);

        $this->assertDatabaseCount('user_availabilities', 0);
    }

    public function test_overlapping_availability_is_rejected(): void
    {
        UserAvailability::factory()->for($this->user)->create([
            'date' => today()->toDateString(),
            'starts_at' => '08:00:00',
            'ends_at' => '14:00:00',
        ]);

        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->set('date', today()->toDateString())
            ->set('startsAt', '13:00')
            ->set('endsAt', '16:00')
            ->call('save')
            ->assertHasErrors(['startsAt']);

        $this->assertDatabaseCount('user_availabilities', 1);
    }

    public function test_adjacent_availabilities_are_allowed(): void
    {
        UserAvailability::factory()->for($this->user)->create([
            'date' => today()->toDateString(),
            'starts_at' => '08:00:00',
            'ends_at' => '14:00:00',
        ]);

        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->set('date', today()->toDateString())
            ->set('startsAt', '14:00')
            ->set('endsAt', '18:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('user_availabilities', 2);
    }

    public function test_update_cannot_create_an_overlap(): void
    {
        UserAvailability::factory()->for($this->user)->create([
            'date' => today()->toDateString(),
            'starts_at' => '08:00:00',
            'ends_at' => '12:00:00',
        ]);
        $afternoon = UserAvailability::factory()->for($this->user)->create([
            'date' => today()->toDateString(),
            'starts_at' => '14:00:00',
            'ends_at' => '18:00:00',
        ]);

        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->call('editAvailability', $afternoon->id)
            ->set('startsAt', '11:00')
            ->set('endsAt', '15:00')
            ->call('save')
            ->assertHasErrors(['startsAt']);

        $afternoon->refresh();
        $this->assertSame('14:00:00', $afternoon->starts_at);
        $this->assertSame('18:00:00', $afternoon->ends_at);
    }

    public function test_user_can_update_and_delete_their_own_availability(): void
    {
        $availability = UserAvailability::factory()->for($this->user)->create([
            'date' => today()->toDateString(),
            'starts_at' => '08:00:00',
            'ends_at' => '14:00:00',
        ]);

        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->call('editAvailability', $availability->id)
            ->assertSet('editorOpen', true)
            ->assertSet('date', today()->toDateString())
            ->set('startsAt', '09:00')
            ->set('endsAt', '15:00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editorOpen', false)
            ->assertSet('successMessage', 'Disponibilità aggiornata.')
            ->call('deleteAvailability', $availability->id)
            ->assertSet('successMessage', 'Disponibilità eliminata.');

        $this->assertDatabaseMissing('user_availabilities', [
            'id' => $availability->id,
        ]);
    }

    public function test_user_cannot_edit_another_users_availability(): void
    {
        $otherUser = User::factory()->create();
        $foreignAvailability = UserAvailability::factory()->for($otherUser)->create([
            'date' => today()->toDateString(),
        ]);

        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->call('editAvailability', $foreignAvailability->id)
            ->assertForbidden();
    }

    public function test_user_cannot_forge_an_edit_for_another_users_availability(): void
    {
        $otherUser = User::factory()->create();
        $foreignAvailability = UserAvailability::factory()->for($otherUser)->create([
            'date' => today()->toDateString(),
        ]);

        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->set('editingId', $foreignAvailability->id)
            ->set('date', today()->toDateString())
            ->set('startsAt', '10:00')
            ->set('endsAt', '12:00')
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseHas('user_availabilities', [
            'id' => $foreignAvailability->id,
            'user_id' => $otherUser->id,
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
        ]);
    }

    public function test_user_cannot_delete_another_users_availability(): void
    {
        $otherUser = User::factory()->create();
        $foreignAvailability = UserAvailability::factory()->for($otherUser)->create([
            'date' => today()->toDateString(),
        ]);

        Livewire::actingAs($this->user)
            ->test(MyAvailability::class)
            ->call('deleteAvailability', $foreignAvailability->id)
            ->assertForbidden();

        $this->assertDatabaseHas('user_availabilities', [
            'id' => $foreignAvailability->id,
        ]);
    }

    public function test_deleting_a_user_also_deletes_their_availabilities(): void
    {
        $availability = UserAvailability::factory()->for($this->user)->create();

        $this->user->delete();

        $this->assertDatabaseMissing('user_availabilities', [
            'id' => $availability->id,
        ]);
    }
}
