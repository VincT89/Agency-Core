<?php

namespace Tests\Feature\Availability;

use App\Domain\Availability\Actions\RecordPresence;
use App\Domain\Availability\Queries\PresenceSummary;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserAvailability;
use App\Models\UserPresenceSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PresenceTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private RecordPresence $record;
    private string $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00', 'Europe/Rome'));
        $this->operator = User::factory()->create(['role' => UserRole::Developer, 'status' => 'active', 'password_changed_at' => now()]);
        $this->record = app(RecordPresence::class);
        $this->connection = hash('sha256', 'presence-test-session');
        $this->actingAs($this->operator);
    }

    private function slot(string $start = '09:00:00', string $end = '13:00:00', ?User $user = null): UserAvailability
    {
        return UserAvailability::factory()->for($user ?? $this->operator)->create([
            'date' => today()->toDateString(), 'starts_at' => $start, 'ends_at' => $end,
        ]);
    }

    private function start(?UserAvailability $slot = null): UserPresenceSession
    {
        $this->record->execute($this->operator, $this->connection, 'start', availabilityId: ($slot ?? $this->slot())->id);

        return UserPresenceSession::where('active_user_id', $this->operator->id)->firstOrFail();
    }

    private function act(UserPresenceSession $session, string $action, string $time): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 '.$time, 'Europe/Rome'));
        $this->record->execute($this->operator, $this->connection, $action, $session->id);
        $session->refresh();
    }

    public function test_check_in_requires_a_current_own_slot_and_is_idempotent(): void
    {
        $slot = $this->slot();
        $session = $this->start($slot);
        $this->start($slot);
        $this->assertDatabaseCount('user_presence_sessions', 1);
        $this->assertDatabaseCount('user_presence_intervals', 1);
        $this->assertSame(now()->timestamp, $session->started_at);
        $this->assertSame('13:00', UserPresenceSession::localTime($session->scheduled_end_at)->format('H:i'));
        $this->assertSame('available', $session->state);
    }

    public function test_guest_suspended_and_uninitialized_accounts_cannot_start_presence(): void
    {
        auth()->logout();
        $this->getJson(route('presence.state'))->assertUnauthorized();
        $this->postJson(route('presence.update'), ['action' => 'start', 'availability_id' => 1])->assertUnauthorized();
        $this->operator->update(['status' => 'inactive']);
        $this->actingAs($this->operator)->postJson(route('presence.update'), ['action' => 'start', 'availability_id' => 1])->assertUnauthorized();
        $this->operator->update(['status' => 'active', 'password_changed_at' => null]);
        $this->actingAs($this->operator)->post(route('presence.update'), ['action' => 'start', 'availability_id' => 1])->assertRedirect(route('password.setup'));
        $this->assertDatabaseCount('user_presence_sessions', 0);
    }

    public function test_endpoint_rejects_foreign_past_future_slots_and_untrusted_actions(): void
    {
        $other = User::factory()->create();
        foreach ([$this->slot(user: $other), $this->slot('08:00:00', '09:00:00'), $this->slot('10:00:00', '11:00:00')] as $slot) {
            $this->postJson(route('presence.update'), ['action' => 'start', 'availability_id' => $slot->id])->assertUnprocessable();
        }
        $this->postJson(route('presence.update'), ['action' => 'rewrite', 'user_id' => $other->id])->assertUnprocessable();
        $this->postJson(route('presence.update'), ['action' => 'heartbeat'])->assertUnprocessable();
        $this->assertDatabaseCount('user_presence_sessions', 0);
    }

    public function test_only_server_time_and_authenticated_identity_are_used_and_csrf_is_required(): void
    {
        $slot = $this->slot();
        $other = User::factory()->create();
        $response = $this->postJson(route('presence.update'), ['action' => 'start', 'availability_id' => $slot->id,
            'user_id' => $other->id, 'started_at' => '2020-01-01', 'extra_confirmed_at' => now()->timestamp, 'available' => 99999])->assertOk();
        $session = UserPresenceSession::sole();
        $this->assertSame($this->operator->id, $session->user_id);
        $this->assertSame(now()->timestamp, $session->started_at);
        $this->assertNull($session->extra_confirmed_at);
        $response->assertJsonPath('state', 'available')->assertJsonPath('owned', true)->assertJsonMissingPath('connection_hash');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->app->instance('env', 'production');
        try {
            $this->postJson(route('presence.update'), ['action' => 'finish', 'presence_id' => $session->id])->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertNull($session->fresh()->ended_at);
    }

    public function test_pause_resume_and_finish_count_only_available_intervals(): void
    {
        $session = $this->start();
        $this->act($session, 'pause', '09:01:00');
        $this->act($session, 'heartbeat', '09:02:00');
        $this->act($session, 'resume', '09:03:00');
        $this->act($session, 'resume', '09:03:10');
        $this->act($session, 'finish', '09:04:00');
        $this->assertSame(['available' => 120, 'paused' => 120, 'within' => 120, 'extra' => 0], $session->durations());
        $this->assertSame('manual', $session->end_reason);
        $this->assertNull($session->active_user_id);
        $this->assertCount(3, $session->intervals);
    }

    public function test_extension_requires_confirmation_after_the_boundary_and_does_not_backfill_waiting_time(): void
    {
        $slot = $this->slot('09:00:00', '09:02:00');
        $this->travelTo(now()->setTime(9, 1, 30));
        $session = $this->start($slot);
        try {
            $this->record->execute($this->operator, $this->connection, 'extend', $session->id);
            $this->fail('Early extension was accepted');
        } catch (ValidationException) {
            $this->assertNull($session->fresh()->extra_confirmed_at);
        }
        $this->act($session, 'heartbeat', '09:02:20');
        $this->assertSame('awaiting_confirmation', $session->state);
        $this->assertSame(30, $session->durations()['available']);
        $this->act($session, 'extend', '09:02:40');
        $this->act($session, 'extend', '09:03:00');
        $this->act($session, 'finish', '09:03:40');
        $this->assertSame(90, $session->durations()['available']);
        $this->assertSame(60, $session->durations()['extra']);
        $this->assertSame('09:02:40', UserPresenceSession::localTime($session->extra_confirmed_at)->format('H:i:s'));
        $this->assertCount(2, $session->intervals);
    }

    public function test_waiting_after_end_does_not_count_as_available_or_paused(): void
    {
        $session = $this->start($this->slot('09:00:00', '09:01:00'));
        $this->act($session, 'heartbeat', '09:01:20');
        $this->act($session, 'heartbeat', '09:02:20');
        $this->act($session, 'finish', '09:03:20');
        $this->assertSame(['available' => 60, 'paused' => 0, 'within' => 60, 'extra' => 0], $session->durations());
    }

    public function test_pauses_are_also_excluded_from_extra_time(): void
    {
        $session = $this->start($this->slot('09:00:00', '09:01:00'));
        $this->act($session, 'extend', '09:01:00');
        $this->act($session, 'pause', '09:01:30');
        $this->act($session, 'resume', '09:02:30');
        $this->act($session, 'finish', '09:03:00');
        $this->assertSame(['available' => 120, 'paused' => 60, 'within' => 60, 'extra' => 60], $session->durations());
    }

    public function test_missing_heartbeats_close_at_last_signal_and_returning_requires_a_new_confirmation(): void
    {
        $slot = $this->slot();
        $session = $this->start($slot);
        $this->act($session, 'heartbeat', '09:01:00');
        $this->travelTo(now()->setTime(9, 3));
        $this->assertSame('disconnected', app(PresenceSummary::class)->personal($this->operator, $this->connection)['state']);
        $this->assertNull($session->fresh()->ended_at);
        $this->act($session, 'heartbeat', '09:04:00');
        $this->assertSame(CarbonImmutable::parse('2026-11-02 09:01')->timestamp, $session->ended_at);
        $this->assertSame(60, $session->durations()['available']);
        $this->assertTrue(app(PresenceSummary::class)->personal($this->operator, $this->connection)['can_start']);
        $new = $this->start($slot);
        $this->assertNotSame($session->id, $new->id);
        $this->assertSame(0, $new->durations()['available']);
        $this->record->execute($this->operator, $this->connection, 'finish', $session->id);
        $this->assertNull($new->fresh()->ended_at);
    }

    public function test_other_device_cannot_renew_presence_but_owner_can_end_it_explicitly(): void
    {
        $session = $this->start();
        $otherConnection = hash('sha256', 'other-presence-test-session');
        $this->travelTo(now()->addMinute());
        try {
            $this->record->execute($this->operator, $otherConnection, 'heartbeat', $session->id);
            $this->fail('Another device renewed the presence');
        } catch (ValidationException) {
            $this->assertSame($session->last_seen_at, $session->fresh()->last_seen_at);
        }
        $this->record->logout($this->operator, $otherConnection);
        $this->assertNull($session->fresh()->ended_at);
        $this->record->execute($this->operator, $otherConnection, 'finish', $session->id);
        $this->assertSame('manual', $session->fresh()->end_reason);
    }

    public function test_real_logout_closes_only_the_confirming_session(): void
    {
        $slot = $this->slot();
        $this->postJson(route('presence.update'), ['action' => 'start', 'availability_id' => $slot->id])->assertOk();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->travelTo(now()->addMinute());
        $this->post(route('logout'))->assertRedirect('/');
        $session = UserPresenceSession::sole();
        $this->assertSame('logout', $session->end_reason);
        $this->assertSame(60, $session->durations()['available']);
    }

    public function test_next_slot_ends_extension_and_requires_a_fresh_check_in(): void
    {
        $first = $this->slot('09:00:00', '09:01:00');
        $next = $this->slot('09:03:00', '09:10:00');
        $session = $this->start($first);
        $this->act($session, 'extend', '09:01:00');
        $this->act($session, 'heartbeat', '09:02:00');
        $this->act($session, 'heartbeat', '09:03:10');
        $this->assertSame('next_boundary', $session->end_reason);
        $this->assertSame(120, $session->durations()['extra']);
        $this->assertSame($next->id, app(PresenceSummary::class)->personal($this->operator, $this->connection)['slot_id']);
        $this->assertSame($next->id, $this->start($next)->availability_id);
    }

    public function test_extension_stops_at_local_midnight(): void
    {
        $this->travelTo(now()->setTime(23, 58));
        $session = $this->start($this->slot('23:58:00', '23:59:00'));
        $this->act($session, 'extend', '23:59:00');
        $this->act($session, 'heartbeat', '23:59:40');
        $this->travelTo(CarbonImmutable::parse('2026-11-03 00:00:10'));
        $this->record->execute($this->operator, $this->connection, 'heartbeat', $session->id);
        $session->refresh();
        $this->assertSame(60, $session->durations()['extra']);
        $this->assertSame('00:00', UserPresenceSession::localTime($session->ended_at)->format('H:i'));
        $this->assertSame('next_boundary', $session->end_reason);
    }

    public function test_slot_changes_or_deletion_do_not_rewrite_confirmed_history(): void
    {
        $slot = $this->slot();
        $session = $this->start($slot);
        $snapshot = $session->only(['scheduled_start_at', 'scheduled_end_at', 'started_at', 'date']);
        $slot->update(['starts_at' => '10:00:00', 'ends_at' => '11:00:00']);
        $slot->delete();
        $this->act($session, 'finish', '09:01:00');
        $this->assertEquals($snapshot, $session->only(array_keys($snapshot)));
        $this->assertNull($session->availability_id);
        $this->assertSame(60, $session->durations()['available']);
    }

    public function test_cleanup_is_idempotent_and_marks_suspended_accounts_without_counting_the_gap(): void
    {
        $session = $this->start();
        $this->act($session, 'heartbeat', '09:01:00');
        $this->operator->update(['status' => 'inactive']);
        $this->travelTo(now()->addSeconds(30));
        $this->artisan('presence:expire')->assertSuccessful();
        $snapshot = $session->fresh()->getAttributes();
        $this->assertSame('account_inactive', $snapshot['end_reason']);
        $this->assertSame(60, $session->fresh()->durations()['available']);
        $this->artisan('presence:expire')->assertSuccessful();
        $this->assertSame($snapshot, $session->fresh()->getAttributes());
    }

    public function test_heartbeat_never_creates_a_session_and_another_user_cannot_finish_one(): void
    {
        $session = $this->start();
        $other = User::factory()->create(['status' => 'active']);
        $this->record->execute($other, $this->connection, 'heartbeat', $session->id);
        $this->record->execute($other, $this->connection, 'finish', $session->id);
        $this->assertDatabaseCount('user_presence_sessions', 1);
        $this->assertNull($session->fresh()->ended_at);
    }

    public function test_four_hour_slot_plus_twenty_five_confirmed_minutes_returns_the_expected_totals(): void
    {
        $session = $this->start();
        while (now()->lt(CarbonImmutable::parse('2026-11-02 13:00'))) {
            $this->travelTo(now()->addMinute());
            $this->record->execute($this->operator, $this->connection, 'heartbeat', $session->id);
        }
        $this->record->execute($this->operator, $this->connection, 'extend', $session->id);
        for ($i = 0; $i < 25; $i++) {
            $this->travelTo(now()->addMinute());
            $this->record->execute($this->operator, $this->connection, 'heartbeat', $session->id);
        }
        $this->record->execute($this->operator, $this->connection, 'finish', $session->id);
        $metrics = $session->fresh()->durations();
        $this->assertSame(15900, $metrics['available']);
        $this->assertSame(1500, $metrics['extra']);
        $this->assertSame('4 h 25 min', UserPresenceSession::durationLabel($metrics['available']));
    }

    public function test_late_arrival_is_not_cancelled_out_by_extra_availability(): void
    {
        $slot = $this->slot('09:00:00', '09:02:00');
        $this->travelTo(now()->addMinute());
        $session = $this->start($slot);
        $this->act($session, 'extend', '09:02:00');
        $this->act($session, 'finish', '09:03:00');
        $this->assertSame('09:01', UserPresenceSession::localTime($session->started_at)->format('H:i'));
        $this->assertSame(['available' => 120, 'paused' => 0, 'within' => 60, 'extra' => 60], $session->durations());
    }

    public function test_elapsed_seconds_are_correct_across_the_daylight_saving_jump(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-29T01:59:00+01:00')->setTimezone('Europe/Rome'));
        $session = $this->start($this->slot('01:00:00', '04:00:00'));
        $this->travelTo(CarbonImmutable::parse('2026-03-29T03:00:00+02:00')->setTimezone('Europe/Rome'));
        $this->record->execute($this->operator, $this->connection, 'finish', $session->id);
        $this->assertSame(60, $session->fresh()->durations()['available']);
        $this->assertSame(0, $session->fresh()->durations()['extra']);
    }

    public function test_database_rejects_a_second_open_presence_for_the_same_operator(): void
    {
        $session = $this->start();
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $session->replicate()->save();
    }

    public function test_scheduler_closes_a_missing_connection_at_its_last_signal(): void
    {
        $session = $this->start();
        $this->act($session, 'heartbeat', '09:01:00');
        $this->travelTo(now()->addMinutes(3));
        $this->artisan('presence:expire')->assertSuccessful();
        $session->refresh();
        $this->assertSame('connection_lost', $session->end_reason);
        $this->assertSame(60, $session->durations()['available']);
        $this->assertTrue(app(PresenceSummary::class)->historyRow($session)['estimated_end']);
        $this->assertSame('09:01', app(PresenceSummary::class)->historyRow($session)['ended']);
    }
}
