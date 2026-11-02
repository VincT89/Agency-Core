<?php

namespace App\Domain\Availability\Queries;

use App\Models\User;
use App\Models\UserAvailability;
use App\Models\UserPresenceSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class PresenceSummary
{
    public function personal(User $user, string $connectionHash): array
    {
        $now = CarbonImmutable::now();
        $slot = UserAvailability::where('user_id', $user->id)->activeAt($now)->first();
        $session = UserPresenceSession::where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNotNull('active_user_id')->orWhereDate('date', $now->toDateString()))
            ->with('intervals')->latest('id')->first();
        $state = $session?->effectiveState($now->timestamp) ?? ($slot ? 'expected' : 'outside');
        $open = in_array($state, ['available', 'paused', 'awaiting_confirmation'], true);
        $owned = $session && hash_equals($session->connection_hash, $connectionHash);
        $metrics = $session?->durations() ?? ['available' => 0, 'paused' => 0, 'extra' => 0];

        return [
            'show' => $slot !== null || $session !== null,
            'state' => $state, 'label' => UserPresenceSession::stateLabel($state),
            'session_id' => $session?->id, 'slot_id' => $slot?->id,
            'slot' => $open && $session ? $this->range($session) : $slot?->timeRangeLabel(),
            'started' => UserPresenceSession::localTime($session?->started_at)?->format('H:i'),
            'available' => UserPresenceSession::durationLabel($metrics['available']),
            'extra' => UserPresenceSession::durationLabel($metrics['extra']),
            'open' => $open, 'owned' => (bool) $owned, 'can_start' => $slot !== null && ! $open,
            'extra_confirmed' => $session?->extra_confirmed_at !== null,
            'heartbeat_seconds' => UserPresenceSession::HEARTBEAT_SECONDS,
        ];
    }

    public function team(): Collection
    {
        $now = CarbonImmutable::now();
        $users = User::where('status', 'active')->with([
            'availabilities' => fn ($q) => $q->whereDate('date', $now->toDateString())->orderBy('starts_at'),
            'presenceSessions' => fn ($q) => $q->whereDate('date', $now->toDateString())->with('intervals')->orderBy('id'),
        ])->orderBy('name')->get();

        return $users->map(function (User $user) use ($now) {
            $slot = $user->availabilities->first(fn ($slot) => $slot->starts_at <= $now->format('H:i:s') && $slot->ends_at > $now->format('H:i:s'));
            $latest = $user->presenceSessions->last();
            $state = $latest?->effectiveState($now->timestamp) ?? ($slot ? 'expected' : 'outside');
            if (! in_array($state, ['available', 'paused', 'awaiting_confirmation'], true)) {
                if ($slot && (! $latest || $latest->availability_id !== $slot->id)) {
                    $state = 'expected';
                } elseif (! $slot && $state !== 'disconnected') {
                    $state = 'outside';
                } elseif ($slot && $state === 'closed') {
                    $state = 'offline';
                }
            }
            $totals = ['available' => 0, 'paused' => 0, 'extra' => 0];
            foreach ($user->presenceSessions as $session) {
                $metrics = $session->durations();
                foreach (array_keys($totals) as $key) {
                    $totals[$key] += $metrics[$key];
                }
            }

            return ['id' => $user->id, 'name' => $user->name, 'state' => $state,
                'label' => $state === 'offline' ? 'Scollegato durante la fascia' : UserPresenceSession::stateLabel($state),
                'slots' => $user->availabilities->map(fn ($slot) => $slot->timeRangeLabel())->all(),
                'started' => UserPresenceSession::localTime($latest?->started_at)?->format('H:i'),
                'ended' => UserPresenceSession::localTime($latest?->effectiveEnd($now->timestamp))?->format('H:i'),
                'estimated_end' => $state === 'disconnected' || $latest?->end_reason === 'connection_lost',
                'available' => UserPresenceSession::durationLabel($totals['available']),
                'extra' => UserPresenceSession::durationLabel($totals['extra']),
            ];
        })->sortBy(fn ($row) => match ($row['state']) {
            'available' => 0, 'expected', 'offline', 'disconnected' => 1,
            'paused', 'awaiting_confirmation' => 2, default => 3,
        })->values();
    }

    public function historyRow(UserPresenceSession $session): array
    {
        $now = CarbonImmutable::now()->timestamp;
        $state = $session->effectiveState($now);
        $durations = $session->durations();
        $reason = match ($session->end_reason) {
            'manual' => 'Uscita confermata', 'logout' => 'Logout', 'account_inactive' => 'Account disattivato',
            'next_boundary' => 'Fine giornata o nuova fascia', default => UserPresenceSession::stateLabel($state),
        };

        return ['slot' => $this->range($session), 'state' => $state, 'reason' => $reason,
            'started' => UserPresenceSession::localTime($session->started_at)->format('H:i'),
            'ended' => UserPresenceSession::localTime($session->effectiveEnd($now))?->format('H:i'),
            'estimated_end' => $state === 'disconnected',
            'available' => UserPresenceSession::durationLabel($durations['available']),
            'paused' => UserPresenceSession::durationLabel($durations['paused']),
            'extra' => UserPresenceSession::durationLabel($durations['extra']),
            'extra_confirmed' => UserPresenceSession::localTime($session->extra_confirmed_at)?->format('H:i'),
        ];
    }

    public function historyDay(Collection $sessions): array
    {
        $sessions = $sessions->sortBy([['started_at', 'asc'], ['id', 'asc']])->values();
        $first = $sessions->first();
        $latest = $this->historyRow($sessions->last());
        $totals = ['available' => 0, 'paused' => 0, 'extra' => 0];
        /** Sum confirmed seconds before formatting, excluding gaps between sessions and preserving partial minutes. */
        foreach ($sessions as $session) {
            $durations = $session->durations();
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $durations[$key];
            }
        }

        return ['key' => $first->user_id.'-'.$first->date->toDateString(),
            'user_id' => $first->user_id, 'name' => $first->user->name, 'date' => $first->date,
            'sessions' => $sessions, 'slot_count' => $sessions->map(fn ($session) => $this->range($session))->unique()->count(),
            'started' => UserPresenceSession::localTime($first->started_at)->format('H:i'),
            'ended' => $latest['ended'], 'estimated_end' => $latest['estimated_end'],
            'state' => $latest['state'], 'reason' => $latest['reason'],
            'available' => UserPresenceSession::durationLabel($totals['available']),
            'paused' => UserPresenceSession::durationLabel($totals['paused']),
            'extra' => UserPresenceSession::durationLabel($totals['extra']),
        ];
    }

    private function range(UserPresenceSession $session): string
    {
        return UserPresenceSession::localTime($session->scheduled_start_at)->format('H:i').'–'.UserPresenceSession::localTime($session->scheduled_end_at)->format('H:i');
    }
}
