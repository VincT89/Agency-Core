<?php

namespace Tests\Feature\Availability;

use App\Domain\Availability\Actions\RecordPresence;
use App\Domain\Availability\Queries\PresenceSummary;
use App\Enums\UserRole;
use App\Livewire\Admin\Availability\TeamPresence;
use App\Livewire\Availability\PresenceHistory;
use App\Models\User;
use App\Models\UserAvailability;
use App\Models\UserPresenceSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PresenceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00', 'Europe/Rome'));
    }

    private function operator(string $name): User
    {
        return User::factory()->create(['name' => $name, 'role' => UserRole::Developer, 'status' => 'active', 'password_changed_at' => now()]);
    }

    private function present(User $user): UserPresenceSession
    {
        $slot = UserAvailability::factory()->for($user)->create(['date' => today()->toDateString(), 'starts_at' => '09:00', 'ends_at' => '13:00']);
        app(RecordPresence::class)->execute($user, hash('sha256', 'test-'.$user->id), 'start', availabilityId: $slot->id);

        return UserPresenceSession::where('user_id', $user->id)->sole();
    }

    public function test_dashboard_presence_is_admin_only_including_livewire_direct_access(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active', 'password_changed_at' => now()]);
            $page = $this->actingAs($user)->get(route('dashboard'))->assertOk();
            if ($role === UserRole::Admin) {
                $page->assertSee('Disponibilità del team');
                Livewire::test(TeamPresence::class)->assertOk();
            } else {
                $page->assertDontSee('Disponibilità del team');
                Livewire::test(TeamPresence::class)->assertForbidden();
            }
        }
    }

    public function test_presence_panel_distinguishes_expected_available_paused_disconnected_and_outside(): void
    {
        $available = $this->operator('Disponibile dimostrativo');
        $paused = $this->operator('Pausa dimostrativa');
        $expected = $this->operator('Atteso dimostrativo');
        $outside = $this->operator('Fuori fascia dimostrativo');
        $this->present($available);
        $session = $this->present($paused);
        app(RecordPresence::class)->execute($paused, hash('sha256', 'test-'.$paused->id), 'pause', $session->id);
        UserAvailability::factory()->for($expected)->create(['date' => today(), 'starts_at' => '09:00', 'ends_at' => '13:00']);
        $rows = app(PresenceSummary::class)->team()->keyBy('id');
        $this->assertSame('available', $rows[$available->id]['state']);
        $this->assertSame('paused', $rows[$paused->id]['state']);
        $this->assertSame('expected', $rows[$expected->id]['state']);
        $this->assertSame('outside', $rows[$outside->id]['state']);
        $this->travelTo(now()->addMinutes(3));
        $this->assertSame('disconnected', app(PresenceSummary::class)->team()->keyBy('id')[$available->id]['state']);
    }

    public function test_personal_history_never_exposes_other_users_even_with_a_forged_user_filter(): void
    {
        $first = $this->operator('Primo operatore dimostrativo');
        $second = $this->operator('Secondo operatore dimostrativo');
        $firstSession = $this->present($first);
        $this->present($second);
        Livewire::actingAs($first)->test(PresenceHistory::class, ['weekStart' => '2026-11-02', 'userId' => $second->id])
            ->assertViewHas('sessions', fn ($rows) => $rows->total() === 1 && $rows->sole()->id === $firstSession->id);
        Livewire::test(PresenceHistory::class, ['weekStart' => '2026-11-02', 'team' => true])->assertForbidden();
        $response = $this->getJson(route('presence.state'))->assertOk();
        $response->assertJsonPath('session_id', $firstSession->id)->assertDontSee($second->name);
    }

    public function test_admin_history_filters_by_week_and_user_and_escapes_names(): void
    {
        $first = $this->operator('<script>presence-demo</script>');
        $second = $this->operator('Secondo operatore dimostrativo');
        $firstSession = $this->present($first);
        $this->present($second);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        Livewire::actingAs($admin)->test(PresenceHistory::class, ['weekStart' => '2026-11-02', 'team' => true])
            ->assertViewHas('sessions', fn ($rows) => $rows->total() === 2)
            ->assertSee('&lt;script&gt;presence-demo&lt;/script&gt;', false)->assertDontSee('<script>presence-demo</script>', false);
        Livewire::test(PresenceHistory::class, ['weekStart' => '2026-11-02', 'team' => true, 'userId' => $first->id])
            ->assertViewHas('sessions', fn ($rows) => $rows->total() === 1 && $rows->sole()->id === $firstSession->id);
        Livewire::test(PresenceHistory::class, ['weekStart' => '2026-10-26', 'team' => true])
            ->assertViewHas('sessions', fn ($rows) => $rows->total() === 0);
    }

    public function test_history_and_presence_are_available_to_commercial_users_without_admin_permissions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Commercial, 'status' => 'active', 'password_changed_at' => now()]);
        $session = $this->present($user);
        $this->actingAs($user)->get(route('availability.index'))->assertOk()->assertSee('Storico presenze');
        Livewire::test(PresenceHistory::class, ['weekStart' => '2026-11-02'])
            ->assertViewHas('sessions', fn ($rows) => $rows->total() === 1 && $rows->sole()->id === $session->id);
        $this->get(route('admin.availability.index'))->assertForbidden();
    }

    public function test_livewire_rechecks_admin_access_on_refresh(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $panel = Livewire::actingAs($admin)->test(TeamPresence::class)->assertOk();
        $admin->update(['role' => UserRole::Developer]);
        $panel->call('$refresh')->assertForbidden();
        $admin->update(['role' => UserRole::Admin]);
        $history = Livewire::test(PresenceHistory::class, ['weekStart' => '2026-11-02', 'team' => true])->assertOk();
        $admin->update(['role' => UserRole::Developer]);
        $history->call('$refresh')->assertForbidden();
    }
}
