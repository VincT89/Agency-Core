<?php

namespace Tests\Feature\Availability;

use App\Domain\Availability\Actions\DeletePresence;
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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeletePresenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private UserAvailability $slot;
    private DeletePresence $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 10:30:00', 'Europe/Rome'));
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->operator = User::factory()->create();
        $this->slot = UserAvailability::factory()->for($this->operator)->create([
            'date' => '2026-11-09', 'starts_at' => '08:00:00', 'ends_at' => '13:00:00',
        ]);
        $this->action = app(DeletePresence::class);
    }

    private function recordedSession(string $start = '08:00:00', string $end = '09:00:00', bool $open = false): UserPresenceSession
    {
        $stamp = fn ($time) => CarbonImmutable::parse('2026-11-09 '.$time, 'Europe/Rome')->timestamp;
        $session = UserPresenceSession::create([
            'user_id' => $this->operator->id, 'active_user_id' => $open ? $this->operator->id : null,
            'availability_id' => $this->slot->id, 'date' => '2026-11-09', 'connection_hash' => hash('sha256', 'delete-test-device'),
            'scheduled_start_at' => $stamp('08:00:00'), 'scheduled_end_at' => $stamp('13:00:00'),
            'extension_deadline_at' => $stamp('23:59:59') + 1, 'started_at' => $stamp($start), 'last_seen_at' => $stamp($end),
            'ended_at' => $open ? null : $stamp($end), 'state' => $open ? 'available' : 'closed', 'end_reason' => $open ? null : 'manual',
        ]);
        $session->intervals()->create(['kind' => 'available', 'started_at' => $stamp($start), 'ended_at' => $open ? null : $stamp($end)]);

        return $session->load('intervals');
    }

    private function history(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($this->admin)->test(PresenceHistory::class, ['weekStart' => '2026-11-09', 'team' => true]);
    }

    public function test_admin_reviews_and_deletes_one_session_with_its_intervals_and_totals_are_recalculated(): void
    {
        $first = $this->recordedSession();
        $second = $this->recordedSession('09:00:00', '10:00:00');
        $before = app(SaveManualPresence::class)->snapshot($first);
        $editor = $this->history()->call('beginDelete', $first->id)->assertSee('Conferma eliminazione');
        $this->assertDatabaseCount('user_presence_sessions', 2);
        $editor->call('deletePresence')->assertHasErrors('form.reason');
        $this->assertDatabaseCount('user_presence_sessions', 2);
        $editor->set('form.reason', '  Presenza duplicata verificata dall’admin.  ')->call('deletePresence')
            ->assertHasNoErrors()->assertSet('editorOpen', false)->assertSee('Presenza eliminata.')
            ->assertViewHas('days', fn ($days) => $days->sole()['available'] === '1 h 0 min' && $days->sole()['started'] === '09:00');
        $this->assertModelMissing($first);
        $this->assertModelExists($second);
        $this->assertDatabaseMissing('user_presence_intervals', ['presence_session_id' => $first->id]);
        $audit = AuditLog::where('action', 'presence_deleted')->sole();
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame('Presenza duplicata verificata dall’admin.', $audit->description);
        $this->assertNull($audit->new_values);
        $this->assertSame($before, array_intersect_key($audit->old_values, $before));
        $this->assertSame($this->operator->name, $audit->old_values['user_name']);
        $this->assertNull($audit->auditable);
        $this->assertArrayNotHasKey('connection_hash', $audit->old_values);
        $this->assertStringNotContainsString($first->connection_hash, json_encode($audit->toArray()));
    }

    public function test_cancel_keeps_the_presence_and_confirmation_cannot_be_skipped(): void
    {
        $session = $this->recordedSession();
        $this->history()->call('beginDelete', $session->id)->call('closeEditor')->assertSet('deletingPresence', false);
        $this->assertModelExists($session);
        $this->assertSame(0, AuditLog::where('action', 'presence_deleted')->count());
        $this->history()->call('deletePresence')->assertForbidden();
        $this->history()->call('beginDelete', $session->id)->call('savePresence')->assertForbidden();
    }

    public function test_live_deletion_stops_the_session_and_late_signals_cannot_restore_it_or_change_a_new_session(): void
    {
        $session = $this->recordedSession('10:00:00', '10:29:30', true);
        $revision = $this->action->revision($session);
        $this->history()->call('beginDelete', $session->id)->assertSee('ancora in corso')
            ->set('form.reason', 'Presenza attiva avviata per errore.')->call('deletePresence')->assertHasNoErrors();
        $record = app(RecordPresence::class);
        $record->execute($this->operator, $session->connection_hash, 'heartbeat', $session->id);
        $this->assertDatabaseCount('user_presence_sessions', 0);
        $this->assertSame(0, $record->expire());
        $this->assertFalse(app(PresenceSummary::class)->personal($this->operator, $session->connection_hash)['open']);
        $record->execute($this->operator, $session->connection_hash, 'start', availabilityId: $this->slot->id);
        $current = UserPresenceSession::with('intervals')->sole();
        $before = app(SaveManualPresence::class)->snapshot($current);
        $this->travelTo(now()->addSeconds(10));
        foreach (['heartbeat', 'finish'] as $action) {
            $record->execute($this->operator, $session->connection_hash, $action, $session->id);
            $this->assertSame($before, app(SaveManualPresence::class)->snapshot($current->fresh('intervals')));
        }
        $this->assertFalse($this->action->execute($this->admin, $session->id, 'Ripetizione della richiesta.', $revision));
        $this->assertSame(1, AuditLog::where('action', 'presence_deleted')->count());
    }

    public function test_a_heartbeat_during_confirmation_is_accepted_and_the_latest_values_are_archived(): void
    {
        $session = $this->recordedSession('10:00:00', '10:29:30', true);
        $editor = $this->history()->call('beginDelete', $session->id)->set('form.reason', 'Cancellazione confermata dopo controllo.');
        $this->travelTo(now()->addSeconds(20));
        app(RecordPresence::class)->execute($this->operator, $session->connection_hash, 'heartbeat', $session->id);
        $editor->call('deletePresence')->assertHasNoErrors();
        $audit = AuditLog::where('action', 'presence_deleted')->sole();
        $this->assertSame(now()->timestamp, $audit->old_values['last_seen_at']);
        $this->assertSame(1820, $audit->old_values['available_seconds']);
    }

    public function test_changed_intervals_require_a_new_confirmation(): void
    {
        $session = $this->recordedSession('10:00:00', '10:29:30', true);
        $editor = $this->history()->call('beginDelete', $session->id)->set('form.reason', 'Eliminazione da verificare.');
        app(RecordPresence::class)->execute($this->operator, $session->connection_hash, 'pause', $session->id);
        $editor->call('deletePresence')->assertHasErrors('form.presence');
        $this->assertModelExists($session);
        $this->assertSame(0, AuditLog::where('action', 'presence_deleted')->count());
    }

    public function test_duplicate_confirmations_do_not_duplicate_the_audit_and_stale_editors_close_on_refresh(): void
    {
        $session = $this->recordedSession();
        $first = $this->history()->call('beginDelete', $session->id)->set('form.reason', 'Eliminazione della presenza duplicata.');
        $second = $this->history()->call('beginDelete', $session->id)->set('form.reason', 'Seconda conferma della stessa presenza.');
        $editing = $this->history()->call('editPresence', $session->id);
        $first->call('deletePresence')->assertHasNoErrors();
        $second->call('deletePresence')->assertHasNoErrors()->assertSee('già stata eliminata');
        $editing->call('$refresh')->assertSet('editorOpen', false)->assertSee('è stata eliminata');
        $this->assertSame(1, AuditLog::where('action', 'presence_deleted')->count());
        $this->assertDatabaseCount('user_presence_sessions', 0);
    }

    #[DataProvider('invalidReasons')]
    public function test_invalid_reasons_leave_the_presence_untouched(mixed $reason): void
    {
        $session = $this->recordedSession();
        $this->expectException(ValidationException::class);
        try {
            $this->action->execute($this->admin, $session->id, $reason, $this->action->revision($session));
        } finally {
            $this->assertModelExists($session);
            $this->assertSame(0, AuditLog::where('action', 'presence_deleted')->count());
        }
    }

    public static function invalidReasons(): array
    {
        return [[''], ['  '], ['a'], [str_repeat('x', 1001)], [['invalid']]];
    }

    public function test_non_admins_and_inactive_admins_cannot_delete(): void
    {
        $session = $this->recordedSession();
        foreach (UserRole::cases() as $role) {
            if ($role === UserRole::Admin) {
                continue;
            }
            $user = User::factory()->create(['role' => $role]);
            Livewire::actingAs($user)->test(PresenceHistory::class, ['weekStart' => '2026-11-09'])
                ->assertDontSee('Elimina presenza')->call('beginDelete', $session->id)->assertForbidden();
            try {
                $this->action->execute($user, $session->id, 'Tentativo non autorizzato.', $this->action->revision($session));
                $this->fail('An operator deleted a presence.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->admin->update(['status' => 'inactive']);
        $this->expectException(HttpException::class);
        $this->action->execute($this->admin, $session->id, 'Tentativo con account inattivo.', $this->action->revision($session));
    }

    public function test_revoked_admin_access_is_checked_at_confirmation_and_an_enabled_developer_can_delete(): void
    {
        $session = $this->recordedSession();
        $developer = User::factory()->create(['role' => UserRole::Developer, 'admin_access' => true]);
        $editor = Livewire::actingAs($developer)->test(PresenceHistory::class, ['weekStart' => '2026-11-09', 'team' => true])
            ->call('beginDelete', $session->id)->set('form.reason', 'Eliminazione da amministratore delegato.');
        $developer->forceFill(['admin_access' => false])->save();
        $editor->call('deletePresence')->assertForbidden();
        $this->assertModelExists($session);
        $developer->forceFill(['admin_access' => true])->save();
        $this->assertTrue($this->action->execute($developer, $session->id, 'Eliminazione autorizzata.', $this->action->revision($session)));
    }

    public function test_history_filters_limit_the_selected_presence(): void
    {
        $session = $this->recordedSession();
        Livewire::actingAs($this->admin)->test(PresenceHistory::class, ['weekStart' => '2026-11-02', 'team' => true])
            ->call('beginDelete', $session->id)->assertNotFound();
        Livewire::test(PresenceHistory::class, ['weekStart' => '2026-11-09', 'team' => true, 'userId' => $this->admin->id])
            ->call('beginDelete', $session->id)->assertNotFound();
        $this->assertModelExists($session);
    }

    public function test_audit_failure_prevents_deletion(): void
    {
        $session = $this->recordedSession();
        $this->mock(AuditLogService::class)->shouldReceive('log')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        try {
            $this->action->execute($this->admin, $session->id, 'Eliminazione con registro non disponibile.', $this->action->revision($session));
            $this->fail('Deletion succeeded without an audit entry.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertModelExists($session);
        $this->assertDatabaseCount('user_presence_intervals', 1);
    }

    public function test_a_failed_delete_rolls_back_its_audit_entry(): void
    {
        $session = $this->recordedSession();
        DB::unprepared("CREATE TRIGGER reject_presence_delete BEFORE DELETE ON user_presence_sessions BEGIN SELECT RAISE(ABORT, 'delete-test-blocked'); END");
        try {
            $this->action->execute($this->admin, $session->id, 'Prova della transazione di eliminazione.', $this->action->revision($session));
            $this->fail('The simulated delete failure was ignored.');
        } catch (QueryException) {
            $this->assertModelExists($session);
            $this->assertDatabaseCount('user_presence_intervals', 1);
            $this->assertSame(0, AuditLog::where('action', 'presence_deleted')->count());
        }
    }

    public function test_deleted_values_and_previous_corrections_remain_readable_and_escaped_in_the_audit(): void
    {
        $this->operator->update(['name' => '<script>operator</script>']);
        $data = ['user_id' => $this->operator->id, 'date' => '2026-11-09', 'availability_id' => $this->slot->id,
            'reason' => 'Inserimento di prova.', 'intervals' => [['kind' => 'available', 'starts_at' => '08:00', 'ends_at' => '09:00']]];
        $manual = app(SaveManualPresence::class);
        $session = $manual->execute($this->admin, $data);
        $oldRevision = $manual->revision($session);
        $data['reason'] = 'Correzione degli orari registrati.';
        $data['intervals'][0]['ends_at'] = '09:15';
        $session = $manual->execute($this->admin, $data, $session->id, $oldRevision);
        $this->action->execute($this->admin, $session->id, '<script>reason</script>', $this->action->revision($session));
        $this->actingAs($this->admin)->get(route('audit-logs.index', ['auditable_type' => 'presences', 'action' => 'presences']))
            ->assertOk()->assertSee('Eliminazione presenza')->assertSee('Dettagli della presenza')
            ->assertSee('08:00:00')->assertSee('09:15:00')->assertSee('Inserimento di prova.')
            ->assertSee('&lt;script&gt;operator&lt;/script&gt;', false)->assertSee('&lt;script&gt;reason&lt;/script&gt;', false)
            ->assertDontSee('<script>operator</script>', false)->assertDontSee('<script>reason</script>', false)
            ->assertDontSee($session->connection_hash, false);
        $this->assertSame(3, AuditLog::where('auditable_type', $session->getMorphClass())->where('auditable_id', $session->id)->count());
        try {
            $manual->execute($this->admin, $data, $session->id, $oldRevision);
            $this->fail('An old correction recreated a deleted presence.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('presence', $exception->errors());
        }
        $this->assertDatabaseCount('user_presence_sessions', 0);
    }

    public function test_delete_request_requires_csrf_protection(): void
    {
        $session = $this->recordedSession();
        $this->post(route('login'), ['email' => $this->admin->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $editor = $this->history()->call('beginDelete', $session->id)->set('form.reason', 'Eliminazione da richiesta di prova.');
        $uri = app('livewire')->getUpdateUri();
        $this->app->instance('env', 'production');
        try {
            $this->postJson($uri, ['components' => [['snapshot' => json_encode($editor->snapshot), 'updates' => [],
                'calls' => [['path' => '', 'method' => 'deletePresence', 'params' => []]]]]])->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertModelExists($session);
        $this->assertSame(0, AuditLog::where('action', 'presence_deleted')->count());
    }
}
