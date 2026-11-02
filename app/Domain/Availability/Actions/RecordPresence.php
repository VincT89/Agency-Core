<?php

namespace App\Domain\Availability\Actions;

use App\Models\User;
use App\Models\UserAvailability;
use App\Models\UserPresenceSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordPresence
{
    public function execute(User $user, string $connectionHash, string $action, ?int $sessionId = null, ?int $availabilityId = null): void
    {
        DB::transaction(function () use ($user, $connectionHash, $action, $sessionId, $availabilityId) {
            $owner = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($owner->status === 'active', 403);
            $now = CarbonImmutable::now();
            $session = UserPresenceSession::where('active_user_id', $owner->id)->first();
            if ($session) {
                $this->settle($session, $now->timestamp);
            }

            if ($action === 'start') {
                if ($session && $session->ended_at === null) {
                    $this->requireConnection($session, $connectionHash);
                    return;
                }
                $slot = UserAvailability::where('user_id', $owner->id)->whereKey($availabilityId)->activeAt($now)->first();
                if (! $slot) {
                    throw ValidationException::withMessages(['presence' => 'Puoi confermare soltanto una tua fascia attualmente in corso.']);
                }
                $start = CarbonImmutable::parse($slot->date->toDateString().' '.$slot->starts_at)->timestamp;
                $end = CarbonImmutable::parse($slot->date->toDateString().' '.$slot->ends_at)->timestamp;
                $session = UserPresenceSession::create([
                    'user_id' => $owner->id, 'active_user_id' => $owner->id, 'availability_id' => $slot->id,
                    'connection_hash' => $connectionHash, 'date' => $slot->date->toDateString(),
                    'scheduled_start_at' => $start, 'scheduled_end_at' => $end,
                    'extension_deadline_at' => $now->startOfDay()->addDay()->timestamp,
                    'started_at' => $now->timestamp, 'last_seen_at' => $now->timestamp, 'state' => 'available',
                ]);
                $this->updateDeadline($session);
                $session->save();
                $session->intervals()->create(['kind' => 'available', 'started_at' => $now->timestamp]);
                return;
            }

            /** A delayed request from another tab must never renew or close a newly started session. */
            if (! $session || $session->id !== $sessionId || $session->ended_at !== null) {
                if (in_array($action, ['heartbeat', 'finish'], true)) {
                    return;
                }
                throw ValidationException::withMessages(['presence' => 'La presenza è terminata. Conferma nuovamente la disponibilità.']);
            }
            if ($action !== 'finish') {
                $this->requireConnection($session, $connectionHash);
            }
            if ($action === 'extend' && $now->timestamp < $session->scheduled_end_at) {
                throw ValidationException::withMessages(['presence' => 'Conferma il tempo aggiuntivo al termine della fascia.']);
            }
            if (in_array($action, ['pause', 'resume'], true) && $session->state === 'awaiting_confirmation') {
                throw ValidationException::withMessages(['presence' => 'La fascia è terminata. Premi “Resto disponibile” per continuare.']);
            }

            $session->last_seen_at = $now->timestamp;
            if ($action === 'finish') {
                $this->close($session, $now->timestamp, 'manual');
            } elseif ($action === 'pause' && $session->state === 'available') {
                $this->transition($session, 'paused', $now->timestamp);
            } elseif ($action === 'resume' && $session->state === 'paused') {
                $this->transition($session, 'available', $now->timestamp);
            } elseif ($action === 'extend' && $session->extra_confirmed_at === null) {
                $session->extra_confirmed_at = $now->timestamp;
                $this->transition($session, 'available', $now->timestamp);
            }
            $session->save();
        }, 3);
    }

    public function logout(User $user, string $connectionHash): void
    {
        DB::transaction(function () use ($user, $connectionHash) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $session = UserPresenceSession::where('active_user_id', $user->id)->where('connection_hash', $connectionHash)->first();
            if ($session) {
                $now = CarbonImmutable::now()->timestamp;
                $this->settle($session, $now);
                if ($session->ended_at === null) {
                    $this->close($session, $now, 'logout');
                }
            }
        }, 3);
    }

    public function expire(): int
    {
        $closed = 0;
        UserPresenceSession::whereNotNull('active_user_id')->orderBy('id')->chunkById(100, function ($sessions) use (&$closed) {
            foreach ($sessions as $candidate) {
                DB::transaction(function () use ($candidate, &$closed) {
                    $user = User::whereKey($candidate->user_id)->lockForUpdate()->first();
                    $session = UserPresenceSession::whereKey($candidate->id)->whereNotNull('active_user_id')->first();
                    if ($user && $session) {
                        $this->settle($session, CarbonImmutable::now()->timestamp);
                        if ($session->ended_at === null && $user->status !== 'active') {
                            $this->close($session, $session->last_seen_at, 'account_inactive');
                        }
                        $closed += (int) ($session->ended_at !== null);
                    }
                }, 3);
            }
        });

        return $closed;
    }

    private function settle(UserPresenceSession $session, int $now): void
    {
        $this->updateDeadline($session);
        if ($now >= $session->last_seen_at + UserPresenceSession::DISCONNECT_SECONDS) {
            $this->close($session, min($session->last_seen_at, $session->extension_deadline_at), 'connection_lost');
        } elseif ($now >= $session->extension_deadline_at) {
            $this->close($session, $session->extension_deadline_at, 'next_boundary');
        } elseif ($now >= $session->scheduled_end_at && $session->extra_confirmed_at === null && $session->state !== 'awaiting_confirmation') {
            $this->transition($session, 'awaiting_confirmation', $session->scheduled_end_at);
        }
        $session->save();
    }

    private function updateDeadline(UserPresenceSession $session): void
    {
        /** A new slot needs its own confirmation; an extension also stops at the end of the local day. */
        $end = UserPresenceSession::localTime($session->scheduled_end_at);
        $next = UserAvailability::where('user_id', $session->user_id)->whereDate('date', $session->date->toDateString())
            ->where('starts_at', '>=', $end->format('H:i:s'))->orderBy('starts_at')->first();
        if ($next) {
            $nextStart = CarbonImmutable::parse($next->date->toDateString().' '.$next->starts_at)->timestamp;
            $session->extension_deadline_at = min($session->extension_deadline_at, $nextStart);
        }
    }

    private function requireConnection(UserPresenceSession $session, string $hash): void
    {
        if (! hash_equals($session->connection_hash, $hash)) {
            throw ValidationException::withMessages(['presence' => 'La disponibilità è attiva in un’altra sessione. Terminala prima di confermarla qui.']);
        }
    }

    private function transition(UserPresenceSession $session, string $state, int $at): void
    {
        $session->intervals()->whereNull('ended_at')->update(['ended_at' => $at]);
        $session->state = $state;
        if (in_array($state, ['available', 'paused'], true)) {
            $session->intervals()->create(['kind' => $state, 'started_at' => $at]);
        }
    }

    private function close(UserPresenceSession $session, int $at, string $reason): void
    {
        $limit = $session->extra_confirmed_at === null ? min($at, $session->scheduled_end_at) : $at;
        $session->intervals()->whereNull('ended_at')->update(['ended_at' => $limit]);
        $session->fill(['ended_at' => $at, 'end_reason' => $reason, 'state' => 'closed', 'active_user_id' => null])->save();
    }
}
