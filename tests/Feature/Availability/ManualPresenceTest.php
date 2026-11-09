<?php

namespace Tests\Feature\Availability;

use App\Domain\Availability\Actions\RecordPresence;
use App\Domain\Availability\Actions\SaveManualPresence;
use App\Domain\Availability\Queries\PresenceSummary;
use App\Enums\UserRole;
use App\Livewire\Availability\PresenceHistory;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserAvailability;
use App\Models\UserPresenceSession;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ManualPresenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private SaveManualPresence $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 18:00:00', 'Europe/Rome'));
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->operator = User::factory()->create();
        $this->action = app(SaveManualPresence::class);
    }

    private function slot(string $start = '08:00:00', string $end = '13:00:00'): UserAvailability
    {
        return UserAvailability::factory()->for($this->operator)->create([
            'date' => '2026-11-09', 'starts_at' => $start, 'ends_at' => $end,
        ]);
    }

    private function data(?UserAvailability $slot = null): array
    {
        return ['user_id' => $this->operator->id, 'date' => '2026-11-09', 'availability_id' => $slot?->id,
            'reason' => 'Correzione concordata dopo la perdita di connessione.',
            'intervals' => [
                ['kind' => 'available', 'starts_at' => '07:45:00', 'ends_at' => '10:00:00'],
                ['kind' => 'paused', 'starts_at' => '10:00:00', 'ends_at' => '10:15:00'],
                ['kind' => 'available', 'starts_at' => '10:15:00', 'ends_at' => '13:30:00'],
            ]];
    }

    private function liveSession(UserAvailability $slot, string $start = '08:00:00', string $lastSeen = '08:30:00'): UserPresenceSession
    {
        $stamp = fn ($time) => CarbonImmutable::parse('2026-11-09 '.$time, 'Europe/Rome')->timestamp;
        $session = UserPresenceSession::create([
            'user_id' => $this->operator->id, 'active_user_id' => $this->operator->id,
            'availability_id' => $slot->id, 'date' => '2026-11-09', 'connection_hash' => hash('sha256', 'test-device'),
            'scheduled_start_at' => $stamp($slot->starts_at), 'scheduled_end_at' => $stamp($slot->ends_at),
            'extension_deadline_at' => CarbonImmutable::parse('2026-11-10 00:00:00', 'Europe/Rome')->timestamp,
            'started_at' => $stamp($start), 'last_seen_at' => $stamp($lastSeen), 'state' => 'available',
        ]);
        $session->intervals()->create(['kind' => 'available', 'started_at' => $stamp($start)]);

        return $session->load('intervals');
    }

    public function test_admin_adds_a_missing_presence_through_the_history_and_totals_include_pauses_and_extra(): void
    {
        $slot = $this->slot();
        $this->actingAs($this->admin)->get(route('admin.availability.index'))->assertOk()->assertSee('Aggiungi presenza');
        Livewire::test(PresenceHistory::class, ['weekStart' => '2026-11-09', 'team' => true])
            ->call('beginCreate')->set('form', $this->data($slot))->call('savePresence')
            ->assertHasNoErrors()->assertSet('editorOpen', false)->assertSee('5 h 30 min')->assertSee('45 min');
        $session = UserPresenceSession::with('intervals')->sole();
        $this->assertSame(['available' => 19800, 'paused' => 900, 'within' => 17100, 'extra' => 2700], $session->durations());
        $this->assertNull($session->active_user_id);
        $this->assertNull($session->extra_confirmed_at);
        $this->assertSame('admin_created', $session->end_reason);
        $audit = AuditLog::where('action', 'presence_created')->sole();
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertNull($audit->old_values);
        $this->assertCount(3, $audit->new_values['intervals']);
        $this->assertArrayNotHasKey('connection_hash', $audit->new_values);
        $this->assertSame(0, AuditLog::where('action', 'login')->count());
    }

    public function test_missing_schedule_counts_all_available_time_as_extra_and_preserves_seconds_and_gaps(): void
    {
        $data = $this->data();
        $data['intervals'] = [
            ['kind' => 'available', 'starts_at' => '08:00:00', 'ends_at' => '08:00:20'],
            ['kind' => 'available', 'starts_at' => '08:10:00', 'ends_at' => '08:10:50'],
        ];
        $session = $this->action->execute($this->admin, $data);
        $this->assertSame(['available' => 70, 'paused' => 0, 'within' => 0, 'extra' => 70], $session->durations());
        Livewire::actingAs($this->operator)->test(PresenceHistory::class, ['weekStart' => '2026-11-09'])
            ->assertSee('Senza fascia programmata')->assertSee('Dettaglio presenze')->assertSee('1 min')
            ->assertViewHas('days', fn ($days) => $days->sole()['slot_count'] === 0)
            ->assertDontSee('Aggiungi presenza')->assertDontSee('Modifica e storico');
    }

    public function test_admin_corrects_a_disconnected_session_and_keeps_the_original_values_in_the_audit(): void
    {
        $slot = $this->slot();
        $session = $this->liveSession($slot);
        $original = $this->action->snapshot($session);
        $device = $session->connection_hash;
        $editor = Livewire::actingAs($this->admin)->test(PresenceHistory::class, ['weekStart' => '2026-11-09', 'team' => true])
            ->call('editPresence', $session->id)->assertSet('form.intervals.0.ends_at', '08:30:00')
            ->set('form', $this->data($slot))->call('savePresence')->assertHasNoErrors()->assertSee('Corretta dall’admin');
        $session->refresh();
        $this->assertNull($session->active_user_id);
        $this->assertSame($device, $session->connection_hash);
        $this->assertSame(19800, $session->durations()['available']);
        $audit = AuditLog::where('action', 'presence_corrected')->sole();
        $this->assertSame($original, $audit->old_values);
        $this->assertSame($this->action->snapshot($session), $audit->new_values);
        $this->assertStringNotContainsString($device, json_encode($audit->toArray()));
        $editor->call('editPresence', $session->id)->assertSee('Storico delle modifiche')->assertSee('08:30:00')
            ->assertSee($this->data()['reason'])->assertDontSee($device, false);
        app(RecordPresence::class)->execute($this->operator, $device, 'heartbeat', $session->id);
        $this->assertSame($audit->new_values, $this->action->snapshot($session->fresh('intervals')));
    }

    public function test_backdated_insertion_does_not_replace_the_current_presence_in_personal_and_team_summaries(): void
    {
        $morning = $this->slot();
        $afternoon = $this->slot('15:00:00', '19:00:00');
        $current = $this->liveSession($afternoon, '15:00:00', '17:59:30');
        $this->action->execute($this->admin, $this->data($morning));
        $personal = app(PresenceSummary::class)->personal($this->operator, $current->connection_hash);
        $this->assertSame($current->id, $personal['session_id']);
        $this->assertTrue($personal['open']);
        $this->assertSame('15:00', app(PresenceSummary::class)->team()->firstWhere('id', $this->operator->id)['started']);
        $this->assertSame($this->operator->id, $current->fresh()->active_user_id);
    }

    public function test_live_presence_cannot_be_overwritten_even_by_an_admin(): void
    {
        $slot = $this->slot('15:00:00', '19:00:00');
        $session = $this->liveSession($slot, '15:00:00', '17:59:30');
        $before = $this->action->snapshot($session);
        Livewire::actingAs($this->admin)->test(PresenceHistory::class, ['weekStart' => '2026-11-09', 'team' => true])
            ->call('editPresence', $session->id)->assertHasErrors('presence');
        try {
            $this->action->execute($this->admin, $this->data($slot), $session->id, $this->action->revision($session));
            $this->fail('An active presence was overwritten.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('presence', $exception->errors());
        }
        $this->assertSame($before, $this->action->snapshot($session->fresh('intervals')));
    }

    public function test_a_stale_editor_cannot_overwrite_another_correction_or_duplicate_its_audit(): void
    {
        $slot = $this->slot();
        $session = $this->liveSession($slot);
        $editor = Livewire::actingAs($this->admin)->test(PresenceHistory::class, ['weekStart' => '2026-11-09', 'team' => true])
            ->call('editPresence', $session->id)->set('form', $this->data($slot));
        $corrected = $this->action->execute($this->admin, $this->data($slot), $session->id, $this->action->revision($session));
        $after = $this->action->snapshot($corrected);
        $editor->call('savePresence')->assertHasErrors('form.presence');
        $this->assertSame($after, $this->action->snapshot($corrected->fresh('intervals')));
        $this->assertSame(1, AuditLog::where('action', 'presence_corrected')->count());
    }

    public function test_overlapping_and_retried_insertions_are_rejected_without_duplicate_time(): void
    {
        $session = $this->action->execute($this->admin, $this->data());
        $this->expectException(ValidationException::class);
        try {
            $this->action->execute($this->admin, $this->data());
        } finally {
            $this->assertDatabaseCount('user_presence_sessions', 1);
            $this->assertSame(1, $session->corrections()->count());
        }
    }

    public function test_adjacent_sessions_are_allowed_but_a_correction_cannot_cover_another_session(): void
    {
        $firstData = $this->data();
        $firstData['intervals'] = [['kind' => 'available', 'starts_at' => '08:00', 'ends_at' => '09:00']];
        $first = $this->action->execute($this->admin, $firstData);
        $secondData = $firstData;
        $secondData['intervals'][0] = ['kind' => 'available', 'starts_at' => '09:00', 'ends_at' => '10:00'];
        $this->action->execute($this->admin, $secondData);
        $firstData['intervals'][0]['ends_at'] = '09:30';
        $this->expectException(ValidationException::class);
        $this->action->execute($this->admin, $firstData, $first->id, $this->action->revision($first));
    }

    #[DataProvider('invalidData')]
    public function test_invalid_intervals_and_missing_reasons_are_rejected_atomically(string $field, mixed $value): void
    {
        $data = $this->data();
        Arr::set($data, $field, $value);
        try {
            $this->action->execute($this->admin, $data);
            $this->fail('Invalid attendance data was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('user_presence_sessions', 0);
            $this->assertDatabaseCount('user_presence_intervals', 0);
            $this->assertSame(0, AuditLog::whereIn('action', ['presence_created', 'presence_corrected'])->count());
        }
    }

    public static function invalidData(): array
    {
        return [
            'missing reason' => ['reason', ''], 'blank reason' => ['reason', '   '],
            'future day' => ['date', '2026-11-10'], 'invalid day' => ['date', '2026-02-30'],
            'unsupported epoch' => ['date', '1960-01-01'],
            'reverse interval' => ['intervals.0.ends_at', '07:00'],
            'overlapping intervals' => ['intervals.1.starts_at', '09:59'],
            'future end' => ['intervals.2.ends_at', '19:00'],
            'invalid time' => ['intervals.2.ends_at', '26:00'],
            'invalid kind' => ['intervals.0.kind', 'outside'],
            'no intervals' => ['intervals', []], 'invalid owner' => ['user_id', 999999],
            'injected interval field' => ['intervals.0.user_id', 999999],
        ];
    }

    public function test_schedule_ownership_and_previous_and_next_boundaries_are_enforced(): void
    {
        $morning = $this->slot();
        $afternoon = $this->slot('15:00:00', '19:00:00');
        foreach ([null, 999999, $afternoon->id] as $slotId) {
            $data = $this->data();
            $data['availability_id'] = $slotId;
            try {
                $this->action->execute($this->admin, $data);
                $this->fail('An invalid schedule was accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('user_presence_sessions', 0);
            }
        }
        $data = $this->data($morning);
        $data['intervals'][2]['ends_at'] = '15:30';
        $this->expectException(ValidationException::class);
        $this->action->execute($this->admin, $data);
    }

    public function test_an_existing_snapshot_survives_deletion_of_the_planned_slot(): void
    {
        $slot = $this->slot();
        $session = $this->liveSession($slot);
        $slot->delete();
        $session->refresh();
        $corrected = $this->action->execute($this->admin, $this->data(), $session->id, $this->action->revision($session));
        $this->assertNull($corrected->availability_id);
        $this->assertSame(17100, $corrected->durations()['within']);
        $this->assertSame('08:00–13:00', app(PresenceSummary::class)->historyRow($corrected)['slot']);
    }

    public function test_correction_cannot_reclassify_a_previous_slot_as_extra_time(): void
    {
        $this->slot();
        $afternoon = $this->slot('15:00:00', '19:00:00');
        $session = $this->liveSession($afternoon, '15:00:00', '15:30:00');
        $this->expectException(ValidationException::class);
        $this->action->execute($this->admin, $this->data($afternoon), $session->id, $this->action->revision($session));
    }

    public function test_a_failed_audit_rolls_back_the_presence_and_its_intervals(): void
    {
        $slot = $this->slot();
        $session = $this->liveSession($slot);
        $before = $this->action->snapshot($session);
        $this->mock(AuditLogService::class)->shouldReceive('log')->once()->andThrow(new \RuntimeException('Audit storage failed'));
        try {
            $this->action->execute($this->admin, $this->data($slot), $session->id, $this->action->revision($session));
            $this->fail('The audit failure was ignored.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit storage failed', $exception->getMessage());
        }
        $this->assertSame($before, $this->action->snapshot($session->fresh('intervals')));
    }

    public function test_non_admin_roles_and_inactive_admins_cannot_use_manual_actions(): void
    {
        foreach (UserRole::cases() as $role) {
            if ($role === UserRole::Admin) {
                continue;
            }
            $user = User::factory()->create(['role' => $role]);
            Livewire::actingAs($user)->test(PresenceHistory::class, ['weekStart' => '2026-11-09'])
                ->call('beginCreate')->assertForbidden();
            try {
                $this->action->execute($user, $this->data());
                $this->fail('A non-administrator saved a presence.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->admin->update(['status' => 'inactive']);
        $this->expectException(HttpException::class);
        $this->action->execute($this->admin, $this->data());
    }

    public function test_permission_revocation_is_rechecked_on_save_and_developer_admin_access_is_supported(): void
    {
        $developer = User::factory()->create(['role' => UserRole::Developer, 'admin_access' => true]);
        $editor = Livewire::actingAs($developer)->test(PresenceHistory::class, ['weekStart' => '2026-11-09', 'team' => true])
            ->call('beginCreate')->set('form', $this->data())->call('savePresence')->assertHasNoErrors()
            ->call('beginCreate')->set('form', $this->data());
        $developer->forceFill(['admin_access' => false])->save();
        $editor->call('savePresence')->assertForbidden();
        $this->assertDatabaseCount('user_presence_sessions', 1);
    }

    public function test_a_correction_cannot_move_a_session_to_another_operator_or_day(): void
    {
        $session = $this->action->execute($this->admin, $this->data());
        $data = $this->data();
        $data['date'] = '2026-11-08';
        $this->expectException(ValidationException::class);
        $this->action->execute($this->admin, $data, $session->id, $this->action->revision($session));
    }

    public function test_reason_and_names_are_escaped_and_corrections_are_filterable_in_the_audit_log(): void
    {
        $this->operator->update(['name' => '<script>operator</script>']);
        $data = $this->data();
        $data['reason'] = '<script>reason</script>';
        $session = $this->action->execute($this->admin, $data);
        Livewire::actingAs($this->admin)->test(PresenceHistory::class, ['weekStart' => '2026-11-09', 'team' => true])
            ->call('editPresence', $session->id)->assertSee('&lt;script&gt;reason&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;operator&lt;/script&gt;', false)
            ->assertDontSee('<script>reason</script>', false)->assertDontSee('<script>operator</script>', false);
        $this->get(route('audit-logs.index', ['auditable_type' => 'presences', 'action' => 'presences']))->assertOk();
    }

    public function test_midnight_ends_the_previous_day_and_dst_invalid_times_are_rejected(): void
    {
        $data = $this->data();
        $data['date'] = '2026-11-08';
        $data['intervals'] = [['kind' => 'available', 'starts_at' => '23:30', 'ends_at' => '00:00']];
        $this->assertSame(1800, $this->action->execute($this->admin, $data)->durations()['extra']);
        foreach (['2026-03-29', '2026-10-25'] as $date) {
            $data['date'] = $date;
            $data['intervals'] = [['kind' => 'available', 'starts_at' => '02:30', 'ends_at' => '03:30']];
            try {
                $this->action->execute($this->admin, $data);
                $this->fail('A DST time was silently normalized.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('intervals.0.starts_at', $exception->errors());
            }
        }
    }

    public function test_manual_livewire_requests_require_csrf_protection(): void
    {
        $this->post(route('login'), ['email' => $this->admin->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $uri = app('livewire')->getUpdateUri();
        $this->app->instance('env', 'production');
        try {
            $this->postJson($uri, ['components' => []])->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseCount('user_presence_sessions', 0);
    }

    public function test_existing_dst_instants_are_preserved_when_the_local_times_are_unchanged(): void
    {
        $start = CarbonImmutable::parse('2026-10-25T02:30:00+02:00')->timestamp;
        $end = CarbonImmutable::parse('2026-10-25T02:30:00+01:00')->timestamp;
        $session = UserPresenceSession::create([
            'user_id' => $this->operator->id, 'date' => '2026-10-25', 'connection_hash' => hash('sha256', 'dst-test'),
            'scheduled_start_at' => $start, 'scheduled_end_at' => $end, 'extension_deadline_at' => $end,
            'started_at' => $start, 'ended_at' => $end, 'last_seen_at' => $end, 'state' => 'closed', 'end_reason' => 'manual',
        ]);
        $session->intervals()->create(['kind' => 'available', 'started_at' => $start, 'ended_at' => $end]);
        $data = $this->data();
        $data['date'] = '2026-10-25';
        $data['intervals'] = [['kind' => 'available', 'starts_at' => '02:30', 'ends_at' => '02:30']];
        $corrected = $this->action->execute($this->admin, $data, $session->id, $this->action->revision($session));
        $this->assertSame(3600, $corrected->durations()['available']);
        $this->assertSame($start, $corrected->started_at);
        $this->assertSame($end, $corrected->ended_at);
    }
}
