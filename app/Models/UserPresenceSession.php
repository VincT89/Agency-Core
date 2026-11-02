<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserPresenceSession extends Model
{
    public const HEARTBEAT_SECONDS = 20;
    public const DISCONNECT_SECONDS = 120;

    protected $guarded = ['id'];
    protected $hidden = ['connection_hash'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'scheduled_start_at' => 'integer', 'scheduled_end_at' => 'integer',
            'extension_deadline_at' => 'integer', 'started_at' => 'integer', 'last_seen_at' => 'integer',
            'extra_confirmed_at' => 'integer', 'ended_at' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function intervals(): HasMany
    {
        return $this->hasMany(UserPresenceInterval::class, 'presence_session_id');
    }

    public static function localTime(?int $timestamp): ?CarbonImmutable
    {
        return $timestamp === null ? null : CarbonImmutable::createFromTimestamp($timestamp, config('app.timezone'));
    }

    public function effectiveState(int $now): string
    {
        if ($this->ended_at !== null) {
            return $this->end_reason === 'connection_lost' ? 'disconnected' : 'closed';
        }
        if ($now >= $this->last_seen_at + self::DISCONNECT_SECONDS) {
            return 'disconnected';
        }
        if ($now >= $this->extension_deadline_at) {
            return 'closed';
        }
        if ($now >= $this->scheduled_end_at && $this->extra_confirmed_at === null) {
            return 'awaiting_confirmation';
        }

        return $this->state;
    }

    public function effectiveEnd(int $now): ?int
    {
        if ($this->ended_at !== null) {
            return $this->ended_at;
        }
        if ($now >= $this->last_seen_at + self::DISCONNECT_SECONDS) {
            return min($this->last_seen_at, $this->extension_deadline_at);
        }

        return $now >= $this->extension_deadline_at ? $this->extension_deadline_at : null;
    }

    public function durations(): array
    {
        $available = $pause = $within = 0;
        $confirmedEnd = $this->confirmedUntil();
        foreach ($this->intervals as $interval) {
            $end = min($interval->ended_at ?? $confirmedEnd, $confirmedEnd);
            $seconds = max(0, $end - $interval->started_at);
            if ($interval->kind === 'paused') {
                $pause += $seconds;
            } else {
                $available += $seconds;
                $within += max(0, min($end, $this->scheduled_end_at) - max($interval->started_at, $this->scheduled_start_at));
            }
        }

        return ['available' => $available, 'paused' => $pause, 'within' => $within, 'extra' => $available - $within];
    }

    public function confirmedUntil(): int
    {
        /** Only confirmed time is counted; a lost connection never accrues the timeout itself. */
        return min($this->ended_at ?? $this->last_seen_at, $this->extension_deadline_at,
            $this->extra_confirmed_at === null ? $this->scheduled_end_at : PHP_INT_MAX);
    }

    public static function durationLabel(int $seconds): string
    {
        $minutes = intdiv(max(0, $seconds), 60);

        return $minutes >= 60 ? intdiv($minutes, 60).' h '.($minutes % 60).' min' : $minutes.' min';
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            'available' => 'Disponibile', 'paused' => 'In pausa',
            'awaiting_confirmation' => 'Fine fascia: conferma richiesta',
            'disconnected' => 'Connessione persa', 'closed' => 'Disponibilità terminata',
            'expected' => 'Atteso, presenza non confermata', default => 'Fuori fascia',
        };
    }
}
