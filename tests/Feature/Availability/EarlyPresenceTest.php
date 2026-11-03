<?php

namespace Tests\Feature\Availability;

use App\Domain\Availability\Actions\RecordPresence;
use App\Domain\Availability\Queries\PresenceSummary;
use App\Livewire\Availability\PresenceHistory;
use App\Models\User;
use App\Models\UserAvailability;
use App\Models\UserPresenceSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EarlyPresenceTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private RecordPresence $record;
    private string $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 08:00:00', 'Europe/Rome'));
        $this->operator = User::factory()->create();
        $this->record = app(RecordPresence::class);
        $this->connection = hash('sha256', 'early-presence-test');
    }

    private function slot(string $start = '09:00:00', string $end = '13:00:00', ?User $user = null, string $date = '2026-11-03'): UserAvailability
    {
        return UserAvailability::factory()->for($user ?? $this->operator)->create([
            'date' => $date, 'starts_at' => $start, 'ends_at' => $end,
        ]);
    }

    private function startEarly(UserAvailability $slot): UserPresenceSession
    {
        $this->record->execute($this->operator, $this->connection, 'start_early', availabilityId: $slot->id);

        return UserPresenceSession::where('active_user_id', $this->operator->id)->sole();
    }

    private function act(UserPresenceSession $session, string $action, string $time): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-03 '.$time, 'Europe/Rome'));
        $this->record->execute($this->operator, $this->connection, $action, $session->id);
        $session->refresh();
    }

    private function keepConnectedUntil(UserPresenceSession $session, string $time): void
    {
        $target = CarbonImmutable::parse('2026-11-03 '.$time, 'Europe/Rome');
        while (now()->lt($target)) {
            $this->travelTo(now()->addMinute()->min($target));
            $this->record->execute($this->operator, $this->connection, 'heartbeat', $session->id);
        }
        $session->refresh();
    }

    public function test_eight_to_thirteen_is_five_available_hours_with_one_hour_extra_in_the_summary_and_daily_history(): void
    {
        $slot = $this->slot();
        $session = $this->startEarly($slot);
        $this->assertSame('09:00', UserPresenceSession::localTime($session->scheduled_start_at)->format('H:i'));
        $this->assertSame('08:00', UserPresenceSession::localTime($session->started_at)->format('H:i'));
        $this->assertNull($session->extra_confirmed_at);
        $this->keepConnectedUntil($session, '09:00:00');
        $this->assertSame(['available' => 3600, 'paused' => 0, 'within' => 0, 'extra' => 3600], $session->durations());
        $this->keepConnectedUntil($session, '13:00:00');
        $this->assertSame(['available' => 18000, 'paused' => 0, 'within' => 14400, 'extra' => 3600], $session->durations());
        $this->assertSame('awaiting_confirmation', $session->state);
        $row = app(PresenceSummary::class)->team()->sole();
        $this->assertSame('5 h 0 min', $row['available']);
        $this->assertSame('1 h 0 min', $row['extra']);
        Livewire::actingAs($this->operator)->test(PresenceHistory::class, ['weekStart' => '2026-11-02'])
            ->assertViewHas('days', fn ($days) => $days->sole()['available'] === '5 h 0 min' && $days->sole()['extra'] === '1 h 0 min');
        $this->assertSame('09:00:00', $slot->fresh()->starts_at);
        $this->assertSame('13:00:00', $slot->fresh()->ends_at);
    }

    public function test_pauses_are_excluded_from_early_extra_and_ordinary_availability(): void
    {
        $session = $this->startEarly($this->slot('08:02:00', '08:04:00'));
        $this->act($session, 'pause', '08:00:30');
        $this->act($session, 'resume', '08:01:30');
        $this->act($session, 'finish', '08:03:00');
        $this->assertSame(['available' => 120, 'paused' => 60, 'within' => 60, 'extra' => 60], $session->durations());
    }

    public function test_early_confirmation_still_requires_resto_disponibile_after_the_scheduled_end(): void
    {
        $session = $this->startEarly($this->slot('08:01:00', '08:02:00'));
        $this->act($session, 'heartbeat', '08:01:00');
        $this->act($session, 'heartbeat', '08:02:00');
        $this->act($session, 'heartbeat', '08:03:00');
        $this->assertSame('awaiting_confirmation', $session->state);
        $this->assertNull($session->extra_confirmed_at);
        $this->assertSame(['available' => 120, 'paused' => 0, 'within' => 60, 'extra' => 60], $session->durations());
        $this->act($session, 'extend', '08:03:00');
        $this->act($session, 'finish', '08:04:00');
        $this->assertSame(['available' => 180, 'paused' => 0, 'within' => 60, 'extra' => 120], $session->durations());
    }

    public function test_disconnection_stops_early_time_at_the_last_signal_and_needs_a_new_confirmation(): void
    {
        $slot = $this->slot();
        $session = $this->startEarly($slot);
        $this->act($session, 'heartbeat', '08:01:00');
        $this->act($session, 'heartbeat', '08:04:00');
        $this->assertSame('connection_lost', $session->end_reason);
        $this->assertSame(['available' => 60, 'paused' => 0, 'within' => 0, 'extra' => 60], $session->durations());
        $this->assertTrue(app(PresenceSummary::class)->personal($this->operator, $this->connection)['can_start_early']);
        $new = $this->startEarly($slot);
        $this->assertNotSame($session->id, $new->id);
        $this->assertSame(0, $new->durations()['available']);
        $this->assertSame('08:04', UserPresenceSession::localTime($new->started_at)->format('H:i'));
    }

    public function test_foreign_past_tomorrow_and_later_slots_cannot_be_used_to_forge_early_availability(): void
    {
        $next = $this->slot();
        $later = $this->slot('15:00:00', '17:00:00');
        $invalid = [$this->slot(user: User::factory()->create()), $this->slot('06:00:00', '07:00:00'),
            $this->slot(date: '2026-11-04'), $later];
        foreach ($invalid as $slot) {
            try {
                $this->startEarly($slot);
                $this->fail('Invalid early slot accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('presence', $exception->errors());
            }
        }
        $this->assertDatabaseCount('user_presence_sessions', 0);
        $this->travelTo(now()->setTime(9, 5));
        try {
            $this->startEarly($later);
            $this->fail('The current slot was skipped');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('presence', $exception->errors());
        }
        $this->assertSame($next->id, app(PresenceSummary::class)->personal($this->operator, $this->connection)['slot_id']);
        $this->assertDatabaseCount('user_presence_sessions', 0);
    }

    public function test_early_action_at_the_exact_start_is_valid_and_does_not_add_extra_time(): void
    {
        $slot = $this->slot();
        $this->travelTo(now()->setTime(8, 59, 59));
        $this->assertTrue(app(PresenceSummary::class)->personal($this->operator, $this->connection)['can_start_early']);
        $this->travelTo(now()->setTime(9, 0));
        $session = $this->startEarly($slot);
        $this->act($session, 'finish', '09:01:00');
        $this->assertSame(['available' => 60, 'paused' => 0, 'within' => 60, 'extra' => 0], $session->durations());
    }

    public function test_no_early_confirmation_is_offered_for_tomorrow_only_or_after_todays_last_slot(): void
    {
        $tomorrow = $this->slot(date: '2026-11-04');
        $this->slot('06:00:00', '07:00:00');
        $row = app(PresenceSummary::class)->personal($this->operator, $this->connection);
        $this->assertFalse($row['can_start_early']);
        $this->assertFalse($row['can_start']);
        $this->assertNull($row['slot_id']);
        $this->expectException(ValidationException::class);
        $this->startEarly($tomorrow);
    }

    public function test_repeated_early_confirmation_does_not_duplicate_sessions_and_another_device_cannot_take_it_over(): void
    {
        $slot = $this->slot();
        $session = $this->startEarly($slot);
        $this->assertSame($session->id, $this->startEarly($slot)->id);
        $this->assertDatabaseCount('user_presence_sessions', 1);
        $this->assertDatabaseCount('user_presence_intervals', 1);
        $this->expectException(ValidationException::class);
        $this->record->execute($this->operator, hash('sha256', 'other-device'), 'start_early', availabilityId: $slot->id);
    }

    public function test_early_endpoint_requires_authentication_csrf_and_a_slot_and_ignores_client_times(): void
    {
        $slot = $this->slot();
        $this->postJson(route('presence.update'), ['action' => 'start_early', 'availability_id' => $slot->id])->assertUnauthorized();
        $this->post(route('login'), ['email' => $this->operator->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $this->getJson(route('presence.state'))->assertOk()->assertJsonPath('can_start_early', true)
            ->assertJsonPath('login_prompt', true)->assertJsonPath('available', '0 min');
        $this->postJson(route('presence.update'), ['action' => 'start_early'])->assertUnprocessable()->assertJsonValidationErrors('availability_id');
        $this->app->instance('env', 'production');
        try {
            $this->postJson(route('presence.update'), ['action' => 'start_early', 'availability_id' => $slot->id])->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseCount('user_presence_sessions', 0);
        $this->postJson(route('presence.update'), ['action' => 'start_early', 'availability_id' => $slot->id,
            'started_at' => 1, 'extra_confirmed_at' => 1, 'user_id' => 99999])->assertOk()
            ->assertJsonPath('started', '08:00')->assertJsonPath('login_prompt', false)->assertJsonPath('owned', true);
        $session = UserPresenceSession::sole();
        $this->assertSame($this->operator->id, $session->user_id);
        $this->assertSame(now()->timestamp, $session->started_at);
        $this->assertNull($session->extra_confirmed_at);
        $this->travelTo(now()->addMinute());
        $this->postJson(route('presence.update'), ['action' => 'heartbeat', 'presence_id' => $session->id])
            ->assertOk()->assertJsonPath('available', '1 min')->assertJsonPath('extra', '1 min');
    }
}
