<?php

namespace Tests\Feature\Availability;

use App\Domain\Availability\Queries\PresenceSummary;
use App\Livewire\Admin\Availability\TeamPresence;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserAvailability;
use App\Models\UserPresenceSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PresenceLoginTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 09:00:00', 'Europe/Rome'));
        $this->operator = User::factory()->create();
    }

    private function signIn(): void
    {
        $this->post(route('login'), ['email' => $this->operator->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        /** Keep the authenticated cookie across requests, as a real browser does. */
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
    }

    private function slot(): UserAvailability
    {
        return UserAvailability::factory()->for($this->operator)->create([
            'date' => '2026-11-03', 'starts_at' => '09:00:00', 'ends_at' => '13:00:00',
        ]);
    }

    public function test_login_is_recorded_once_before_confirmation_and_visible_in_the_admin_summary(): void
    {
        $this->slot();
        $this->signIn();
        $this->assertSame(1, AuditLog::where('action', 'login')->where('user_id', $this->operator->id)->count());
        $this->assertSame(now()->timestamp, session('presence.login_at'));
        $this->getJson(route('presence.state'))->assertOk()
            ->assertJsonPath('login_prompt', true)->assertJsonPath('accessed', '09:00')
            ->assertJsonPath('can_start', true)->assertJsonPath('started', null)->assertJsonPath('available', '0 min');
        $this->assertDatabaseCount('user_presence_sessions', 0);
        $this->assertDatabaseCount('user_presence_intervals', 0);
        $row = app(PresenceSummary::class)->team()->sole();
        $this->assertSame('09:00', $row['accessed']);
        $this->assertNull($row['started']);
        $this->assertSame('expected', $row['state']);
        $this->assertSame('Accesso registrato, disponibilità da confermare', $row['label']);
        Livewire::actingAs($this->operator)->test(TeamPresence::class)->assertForbidden();
    }

    public function test_availability_starts_at_confirmation_and_client_supplied_login_times_are_ignored(): void
    {
        $slot = $this->slot();
        $this->signIn();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 09:05:00', 'Europe/Rome'));
        $this->postJson(route('presence.update'), ['action' => 'start', 'availability_id' => $slot->id,
            'login_at' => 1, 'started_at' => 1, 'user_id' => 99999])->assertOk()
            ->assertJsonPath('login_prompt', false)->assertJsonPath('accessed', '09:00')
            ->assertJsonPath('started', '09:05')->assertJsonPath('available', '0 min');
        $session = UserPresenceSession::sole();
        $this->assertSame(now()->timestamp, $session->started_at);
        $this->assertSame(now()->timestamp, $session->intervals()->sole()->started_at);
        $this->travelTo(now()->addMinute());
        $this->postJson(route('presence.update'), ['action' => 'heartbeat', 'presence_id' => $session->id])
            ->assertOk()->assertJsonPath('available', '1 min')->assertJsonPath('accessed', '09:00');
    }

    public function test_not_yet_and_navigation_do_not_start_a_presence_or_repeat_the_login_prompt(): void
    {
        $this->slot();
        $this->signIn();
        $this->postJson(route('presence.update'), ['action' => 'defer'])->assertOk()
            ->assertJsonPath('login_prompt', false)->assertJsonPath('can_start', true)->assertJsonPath('accessed', '09:00');
        $this->get(route('availability.index'))->assertOk();
        $this->getJson(route('presence.state'))->assertOk()->assertJsonPath('login_prompt', false);
        $this->assertDatabaseCount('user_presence_sessions', 0);
        $this->assertDatabaseCount('user_presence_intervals', 0);
        $this->assertSame(1, AuditLog::where('action', 'login')->count());
    }

    public function test_a_new_login_prompts_again_and_shows_the_latest_access_separately(): void
    {
        $this->signIn();
        $this->postJson(route('presence.update'), ['action' => 'defer'])->assertOk();
        $this->post(route('logout'))->assertRedirect('/');
        $this->travelTo(now()->addHour());
        $this->signIn();
        $this->getJson(route('presence.state'))->assertOk()->assertJsonPath('login_prompt', true)->assertJsonPath('accessed', '10:00');
        $this->assertSame(2, AuditLog::where('action', 'login')->count());
        $this->assertSame('10:00', app(PresenceSummary::class)->team()->sole()['accessed']);
    }

    public function test_login_before_the_slot_is_recorded_without_creating_availability_or_extra_time(): void
    {
        $slot = $this->slot();
        $this->travelTo(now()->setTime(8, 50));
        $this->signIn();
        $this->getJson(route('presence.state'))->assertOk()->assertJsonPath('login_prompt', true)
            ->assertJsonPath('accessed', '08:50')->assertJsonPath('can_start', false)
            ->assertJsonPath('can_start_early', true)->assertJsonPath('available', '0 min');
        $this->postJson(route('presence.update'), ['action' => 'start', 'availability_id' => $slot->id])
            ->assertUnprocessable();
        $this->getJson(route('presence.state'))->assertOk()->assertJsonPath('login_prompt', true);
        $this->assertDatabaseCount('user_presence_sessions', 0);
    }

    public function test_an_existing_authenticated_session_prompts_before_the_slot_without_a_new_login(): void
    {
        $this->slot();
        $this->travelTo(now()->setTime(8, 0));
        $this->actingAs($this->operator);

        $this->getJson(route('presence.state'))->assertOk()
            ->assertJsonPath('login_prompt', true)->assertJsonPath('can_start_early', true)
            ->assertJsonPath('accessed', null)->assertJsonPath('available', '0 min');
        $this->assertSame(0, AuditLog::where('action', 'login')->count());
        $this->assertDatabaseCount('user_presence_sessions', 0);
    }

    public function test_a_previous_days_answer_does_not_suppress_the_prompt_before_todays_slot(): void
    {
        $this->travelTo(now()->subDay());
        $this->signIn();
        $this->postJson(route('presence.update'), ['action' => 'defer'])->assertOk()
            ->assertJsonPath('login_prompt', false);
        $this->travelTo(CarbonImmutable::parse('2026-11-03 08:00:00', 'Europe/Rome'));
        $this->slot();

        $this->getJson(route('presence.state'))->assertOk()
            ->assertJsonPath('login_prompt', true)->assertJsonPath('can_start_early', true)
            ->assertJsonPath('accessed', null)->assertJsonPath('available', '0 min');
        $this->postJson(route('presence.update'), ['action' => 'defer'])->assertOk()
            ->assertJsonPath('login_prompt', false);
        $this->get(route('availability.index'))->assertOk();
        $this->getJson(route('presence.state'))->assertOk()->assertJsonPath('login_prompt', false);
        $this->assertSame(1, AuditLog::where('action', 'login')->count());
        $this->assertDatabaseCount('user_presence_sessions', 0);
        $this->assertDatabaseCount('user_presence_intervals', 0);
    }

    public function test_failed_or_suspended_logins_do_not_record_an_access_or_prompt(): void
    {
        $this->post(route('login'), ['email' => $this->operator->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->operator->update(['status' => 'inactive']);
        $this->post(route('login'), ['email' => $this->operator->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertSame(0, AuditLog::where('action', 'login')->count());
        $this->assertNull(session('presence.login_at'));
        $this->assertGuest();
    }

    public function test_only_todays_login_is_shown_for_its_operator_and_other_audit_actions_are_ignored(): void
    {
        AuditLog::create(['user_id' => $this->operator->id, 'action' => 'login', 'auditable_type' => User::class,
            'auditable_id' => $this->operator->id, 'created_at' => now()->subDay()]);
        AuditLog::create(['user_id' => $this->operator->id, 'action' => 'updated', 'auditable_type' => User::class,
            'auditable_id' => $this->operator->id, 'created_at' => now()]);
        $other = User::factory()->create();
        AuditLog::create(['user_id' => $other->id, 'action' => 'login', 'auditable_type' => User::class,
            'auditable_id' => $other->id, 'created_at' => now()->setTime(8, 30)]);
        $rows = app(PresenceSummary::class)->team()->keyBy('id');
        $this->assertNull($rows[$this->operator->id]['accessed']);
        $this->assertSame('08:30', $rows[$other->id]['accessed']);
    }

    public function test_deferring_requires_authentication_and_csrf(): void
    {
        $this->postJson(route('presence.update'), ['action' => 'defer'])->assertUnauthorized();
        $this->signIn();
        $this->app->instance('env', 'production');
        try {
            $this->postJson(route('presence.update'), ['action' => 'defer'])->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertTrue(session('presence.login_prompt'));
    }
}
