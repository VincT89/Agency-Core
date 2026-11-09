<?php

namespace App\Domain\Availability\Actions;

use App\Models\User;
use App\Models\UserPresenceSession;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class DeletePresence
{
    public function execute(User $actor, int $sessionId, mixed $reason, string $revision): bool
    {
        $actor = $actor->fresh();
        abort_unless($actor?->status === 'active' && $actor->canManageSystem(), 403);
        $data = Validator::make(['reason' => is_string($reason) ? trim($reason) : null], [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'reason.required' => 'Indica il motivo della cancellazione.',
            'reason.min' => 'Descrivi il motivo con almeno 3 caratteri.',
            'reason.max' => 'Il motivo può contenere al massimo 1000 caratteri.',
        ])->validate();
        $ownerId = UserPresenceSession::whereKey($sessionId)->value('user_id');
        if ($ownerId === null) {
            return false;
        }

        return DB::transaction(function () use ($actor, $sessionId, $data, $revision, $ownerId) {
            /** Use the same operator lock as heartbeat and corrections before removing an active presence. */
            $owner = User::whereKey($ownerId)->lockForUpdate()->first();
            $session = UserPresenceSession::with('intervals')->whereKey($sessionId)->lockForUpdate()->first();
            if (! $owner || ! $session) {
                return false;
            }
            abort_unless($session->user_id === $owner->id, 403);
            if (! hash_equals($this->revision($session), $revision)) {
                throw ValidationException::withMessages(['presence' => 'La presenza è cambiata. Riapri la conferma di eliminazione per controllare i dati aggiornati.']);
            }

            $durations = $session->durations();
            $before = array_merge(app(SaveManualPresence::class)->snapshot($session), [
                'user_name' => $owner->name,
                'confirmed_until' => $session->confirmedUntil(),
                'available_seconds' => $durations['available'], 'paused_seconds' => $durations['paused'],
                'extra_seconds' => $durations['extra'],
            ]);
            /** The audit survives the deleted record and rolls back with it if either write fails. */
            app(AuditLogService::class)->log('presence_deleted', $session, $before, null, $data['reason'], $actor->id);
            $session->delete();

            return true;
        }, 3);
    }

    public function revision(UserPresenceSession $session): string
    {
        $values = app(SaveManualPresence::class)->snapshot($session);
        /** Regular heartbeat updates remain valid while the confirmation is open; the audit captures their latest value. */
        unset($values['last_seen_at']);

        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }
}
