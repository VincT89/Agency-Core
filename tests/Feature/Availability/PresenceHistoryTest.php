<?php

namespace Tests\Feature\Availability;

use App\Enums\UserRole;
use App\Livewire\Availability\PresenceHistory;
use App\Models\User;
use App\Models\UserPresenceInterval;
use App\Models\UserPresenceSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PresenceHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 18:00:00', 'Europe/Rome'));
    }

    private function recordedSession(User $user, string $date, array $intervals, string $slotStart = '09:00:00', string $slotEnd = '13:00:00', bool $open = false, ?string $extra = null): UserPresenceSession
    {
        $stamp = fn ($time) => CarbonImmutable::parse($date.' '.$time, 'Europe/Rome')->timestamp;
        $last = $intervals[array_key_last($intervals)];
        $session = UserPresenceSession::create([
            'user_id' => $user->id, 'connection_hash' => hash('sha256', 'test-'.$user->id),
            'active_user_id' => $open ? $user->id : null, 'date' => $date,
            'scheduled_start_at' => $stamp($slotStart), 'scheduled_end_at' => $stamp($slotEnd),
            'extension_deadline_at' => CarbonImmutable::parse($date, 'Europe/Rome')->addDay()->startOfDay()->timestamp,
            'started_at' => $stamp($intervals[0][1]), 'last_seen_at' => $stamp($last[2]),
            'ended_at' => $open ? null : $stamp($last[2]), 'extra_confirmed_at' => $extra ? $stamp($extra) : null,
            'state' => $open ? $last[0] : 'closed', 'end_reason' => $open ? null : 'manual',
        ]);
        foreach ($intervals as $index => [$kind, $start, $end]) {
            $session->intervals()->create(['kind' => $kind, 'started_at' => $stamp($start),
                'ended_at' => $open && $index === array_key_last($intervals) ? null : $stamp($end)]);
        }

        return $session;
    }

    public function test_day_sums_confirmed_time_pauses_and_extra_without_counting_the_gap_between_slots_or_changing_records(): void
    {
        $user = User::factory()->create();
        $this->recordedSession($user, '2026-11-02', [
            ['available', '09:00:00', '10:00:00'], ['paused', '10:00:00', '10:15:00'],
            ['available', '10:15:00', '13:00:00'],
        ]);
        $this->recordedSession($user, '2026-11-02', [
            ['available', '15:00:00', '17:00:00'], ['paused', '17:00:00', '17:10:00'],
            ['available', '17:10:00', '17:40:00'],
        ], '15:00:00', '17:00:00', extra: '17:00:00');
        $before = [UserPresenceSession::all()->toArray(), UserPresenceInterval::all()->toArray()];

        Livewire::actingAs($user)->test(PresenceHistory::class, ['weekStart' => '2026-11-02'])
            ->assertViewHas('days', function ($days) use ($user) {
                $this->assertSame(1, $days->total());
                $day = $days->sole();
                $this->assertSame($user->id, $day['user_id']);
                $this->assertSame('2026-11-02', $day['date']->toDateString());
                $this->assertSame('09:00', $day['started']);
                $this->assertSame('17:40', $day['ended']);
                $this->assertSame('6 h 15 min', $day['available']);
                $this->assertSame('25 min', $day['paused']);
                $this->assertSame('30 min', $day['extra']);
                $this->assertSame(2, $day['slot_count']);
                $this->assertCount(2, $day['sessions']);

                return true;
            })->assertSee('Disponibilità totale')->assertSee('2 fasce')->assertSee('09:00–13:00')->assertSee('15:00–17:00');

        $this->assertSame($before, [UserPresenceSession::all()->toArray(), UserPresenceInterval::all()->toArray()]);
    }

    public function test_reconnections_share_one_day_and_one_slot_and_seconds_are_summed_before_rounding(): void
    {
        $user = User::factory()->create();
        foreach ([['09:00:00', '09:00:20'], ['09:00:30', '09:00:50'], ['09:01:00', '09:01:20']] as [$start, $end]) {
            $this->recordedSession($user, '2026-11-02', [['available', $start, $end]]);
        }

        Livewire::actingAs($user)->test(PresenceHistory::class, ['weekStart' => '2026-11-02'])
            ->assertViewHas('days', function ($days) {
                $day = $days->sole();
                $this->assertSame('1 min', $day['available']);
                $this->assertSame(1, $day['slot_count']);
                $this->assertCount(3, $day['sessions']);

                return true;
            })->assertSee('1 fascia')->assertDontSee('3 fasce');
    }

    public function test_days_and_operators_stay_separate_and_filters_apply_to_the_whole_day(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        foreach (['2026-11-02', '2026-11-03'] as $date) {
            $this->recordedSession($first, $date, [['available', '09:00:00', '10:00:00']]);
            $this->recordedSession($first, $date, [['available', '15:00:00', '16:00:00']], '15:00:00', '17:00:00');
        }
        $this->recordedSession($second, '2026-11-02', [['available', '09:00:00', '09:30:00']]);
        $this->recordedSession($first, '2026-10-30', [['available', '09:00:00', '10:00:00']]);

        Livewire::actingAs($admin)->test(PresenceHistory::class, ['weekStart' => '2026-11-02', 'team' => true])
            ->assertViewHas('days', fn ($days) => $days->total() === 3);
        Livewire::test(PresenceHistory::class, ['weekStart' => '2026-11-02', 'team' => true, 'userId' => $first->id])
            ->assertViewHas('days', function ($days) use ($first) {
                $this->assertSame(2, $days->total());
                $this->assertSame(['2026-11-03', '2026-11-02'], $days->getCollection()->map(fn ($day) => $day['date']->toDateString())->all());
                foreach ($days as $day) {
                    $this->assertSame($first->id, $day['user_id']);
                    $this->assertSame('2 h 0 min', $day['available']);
                    $this->assertCount(2, $day['sessions']);
                }

                return true;
            })->assertDontSee($second->name);
    }

    public function test_pagination_keeps_all_sessions_of_a_day_together_even_after_twenty_reconnections(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        for ($index = 0; $index < 25; $index++) {
            $start = CarbonImmutable::parse('2026-11-02 09:00:00')->addSeconds($index * 30);
            $this->recordedSession($user, '2026-11-02', [['available', $start->format('H:i:s'), $start->addSeconds(20)->format('H:i:s')]]);
        }
        for ($index = 0; $index < 20; $index++) {
            $this->recordedSession(User::factory()->create(), '2026-11-02', [['available', '09:00:00', '10:00:00']]);
        }

        Livewire::actingAs($admin)->test(PresenceHistory::class, ['weekStart' => '2026-11-02', 'team' => true])
            ->assertViewHas('days', function ($days) use ($user) {
                $this->assertSame(21, $days->total());
                $this->assertCount(20, $days);
                $day = $days->getCollection()->firstWhere('user_id', $user->id);
                $this->assertCount(25, $day['sessions']);
                $this->assertSame('8 min', $day['available']);

                return true;
            })->call('setPage', 2, 'presencePage')->assertViewHas('days', function ($days) use ($user) {
                $this->assertSame(2, $days->currentPage());
                $this->assertCount(1, $days);
                $this->assertNotSame($user->id, $days->sole()['user_id']);

                return true;
            });
    }

    public function test_week_includes_sunday_and_excludes_the_following_monday(): void
    {
        $user = User::factory()->create();
        $this->recordedSession($user, '2026-11-08', [['available', '09:00:00', '10:00:00']]);
        $this->recordedSession($user, '2026-11-09', [['available', '09:00:00', '10:00:00']]);

        Livewire::actingAs($user)->test(PresenceHistory::class, ['weekStart' => '2026-11-02'])
            ->assertViewHas('days', fn ($days) => $days->total() === 1 && $days->sole()['date']->toDateString() === '2026-11-08');
    }

    public function test_open_afternoon_session_sets_the_day_state_and_disconnection_does_not_add_unconfirmed_time(): void
    {
        $user = User::factory()->create();
        $this->recordedSession($user, '2026-11-02', [['available', '09:00:00', '13:00:00']]);
        $this->recordedSession($user, '2026-11-02', [['available', '15:00:00', '15:01:00']], '15:00:00', '17:00:00', open: true);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 15:01:10', 'Europe/Rome'));

        $history = Livewire::actingAs($user)->test(PresenceHistory::class, ['weekStart' => '2026-11-02'])
            ->assertViewHas('days', function ($days) {
                $day = $days->sole();
                $this->assertSame('available', $day['state']);
                $this->assertSame('09:00', $day['started']);
                $this->assertNull($day['ended']);
                $this->assertSame('4 h 1 min', $day['available']);

                return true;
            });

        $this->travelTo(CarbonImmutable::parse('2026-11-02 15:04:00', 'Europe/Rome'));
        $history->call('$refresh')->assertViewHas('days', function ($days) {
            $day = $days->sole();
            $this->assertSame('disconnected', $day['state']);
            $this->assertSame('15:01', $day['ended']);
            $this->assertTrue($day['estimated_end']);
            $this->assertSame('4 h 1 min', $day['available']);

            return true;
        });
    }
}
