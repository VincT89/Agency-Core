<?php

namespace App\Domain\Availability\Actions;

use App\Models\User;
use App\Models\UserAvailability;
use App\Models\UserPresenceSession;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveManualPresence
{
    public function execute(User $actor, array $data, ?int $sessionId = null, ?string $revision = null): UserPresenceSession
    {
        $actor = $actor->fresh();
        abort_unless($actor?->status === 'active' && $actor->canManageSystem(), 403);
        $data['reason'] = is_string($data['reason'] ?? null) ? trim($data['reason']) : null;
        $data = Validator::make($data, [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1970-01-02', 'before_or_equal:today'],
            'availability_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'intervals' => ['required', 'array', 'min:1', 'max:60'],
            'intervals.*' => ['required', 'array:kind,starts_at,ends_at'],
            'intervals.*.kind' => ['required', Rule::in(['available', 'paused'])],
            'intervals.*.starts_at' => ['required', 'date_format:H:i,H:i:s'],
            'intervals.*.ends_at' => ['required', 'date_format:H:i,H:i:s'],
        ], [
            'date.before_or_equal' => 'Puoi registrare presenze soltanto fino a oggi.',
            'reason.required' => 'Indica il motivo della registrazione o della correzione.',
            'reason.min' => 'Descrivi il motivo con almeno 3 caratteri.',
            'reason.max' => 'Il motivo può contenere al massimo 1000 caratteri.',
            'intervals.*.starts_at.*' => 'Indica un orario di ingresso valido.',
            'intervals.*.ends_at.*' => 'Indica un orario di uscita valido.',
        ])->validate();

        return DB::transaction(function () use ($actor, $data, $sessionId, $revision) {
            /** Share the operator lock with heartbeat, expiry and scheduling so corrections cannot race with them. */
            $owner = User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            $session = $sessionId === null ? null : UserPresenceSession::with('intervals')->lockForUpdate()->find($sessionId);
            if ($sessionId !== null && $session === null) {
                throw ValidationException::withMessages(['presence' => 'La presenza è stata eliminata. I dati precedenti sono consultabili nel registro attività.']);
            }
            $now = CarbonImmutable::now();
            $day = CarbonImmutable::parse($data['date'])->startOfDay();
            $before = null;
            if ($session) {
                abort_unless($session->user_id === $owner->id, 403);
                if ($session->date->toDateString() !== $data['date']
                    || $session->availability_id !== (isset($data['availability_id']) ? (int) $data['availability_id'] : null)) {
                    throw ValidationException::withMessages(['presence' => 'Operatore, giorno e fascia della presenza esistente devono essere conservati.']);
                }
                if ($session->effectiveEnd($now->timestamp) === null) {
                    throw ValidationException::withMessages(['presence' => 'La presenza è ancora in corso. Puoi correggerla dopo la conclusione o la disconnessione.']);
                }
                if ($revision === null || ! hash_equals($this->revision($session), $revision)) {
                    throw ValidationException::withMessages(['presence' => 'La presenza è cambiata mentre il modulo era aperto. Riapri la modifica per usare i dati aggiornati.']);
                }
                $before = $this->snapshot($session);
            } else {
                $session = $this->newSession($owner, $data, $day);
            }

            $originals = $session->intervals->sortBy('started_at')->values();
            $intervals = [];
            $previousEnd = null;
            foreach (array_values($data['intervals']) as $index => $interval) {
                $start = $this->timestamp($day, $interval['starts_at'], "intervals.$index.starts_at", $originals->get($index)?->started_at);
                $endDay = in_array($interval['ends_at'], ['00:00', '00:00:00'], true) ? $day->addDay() : $day;
                $end = $this->timestamp($endDay, $interval['ends_at'], "intervals.$index.ends_at", $originals->get($index)?->ended_at);
                if ($end <= $start || ($previousEnd !== null && $start < $previousEnd)) {
                    throw ValidationException::withMessages(["intervals.$index.ends_at" => 'Gli intervalli devono essere in ordine, con uscita successiva all’ingresso e senza sovrapposizioni.']);
                }
                if ($end > $now->timestamp || $end > $session->extension_deadline_at) {
                    throw ValidationException::withMessages(["intervals.$index.ends_at" => 'L’uscita deve essere già trascorsa e rientrare nella giornata, prima dell’eventuale fascia successiva.']);
                }
                $intervals[] = ['kind' => $interval['kind'], 'started_at' => $start, 'ended_at' => $end];
                $previousEnd = $end;
            }
            $start = $intervals[0]['started_at'];
            $end = $intervals[array_key_last($intervals)]['ended_at'];
            if ($session->availability_id !== null) {
                $previousSlotEnd = UserAvailability::where('user_id', $owner->id)->whereDate('date', $data['date'])
                    ->where('ends_at', '<=', UserPresenceSession::localTime($session->scheduled_start_at)->format('H:i:s'))
                    ->orderByDesc('ends_at')->value('ends_at');
                if ($previousSlotEnd !== null && $start < CarbonImmutable::parse($data['date'].' '.$previousSlotEnd)->timestamp) {
                    throw ValidationException::withMessages(['presence' => 'L’ingresso ricade in una fascia precedente. Registra separatamente le presenze delle diverse fasce.']);
                }
            }
            $others = UserPresenceSession::where('user_id', $owner->id)->where('started_at', '<', $end)
                ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $start))
                ->when($session->exists, fn ($query) => $query->whereKeyNot($session->id))->get();
            foreach ($others as $other) {
                if (($other->effectiveEnd($now->timestamp) ?? $now->timestamp) > $start) {
                    throw ValidationException::withMessages(['presence' => 'Gli orari si sovrappongono a un’altra presenza dell’operatore. Correggi gli intervalli della presenza già registrata.']);
                }
            }

            $session->fill([
                'started_at' => $start, 'ended_at' => $end, 'last_seen_at' => $end,
                'state' => 'closed', 'active_user_id' => null,
                'end_reason' => $session->exists ? 'admin_corrected' : 'admin_created',
            ])->save();
            $session->intervals()->delete();
            $session->intervals()->createMany($intervals);
            $session->load('intervals');
            /** Keep the original values and the audit entry in the same transaction, without device identifiers. */
            app(AuditLogService::class)->log($before === null ? 'presence_created' : 'presence_corrected', $session,
                $before, $this->snapshot($session), $data['reason'], $actor->id);

            return $session;
        }, 3);
    }

    public function snapshot(UserPresenceSession $session): array
    {
        return array_merge($session->only([
            'user_id', 'availability_id', 'active_user_id', 'scheduled_start_at', 'scheduled_end_at',
            'extension_deadline_at', 'started_at', 'ended_at', 'last_seen_at', 'extra_confirmed_at', 'state', 'end_reason',
        ]), [
            'date' => $session->date->toDateString(),
            'intervals' => $session->intervals->sortBy('started_at')->values()
                ->map(fn ($interval) => $interval->only(['id', 'kind', 'started_at', 'ended_at']))->all(),
        ]);
    }

    public function revision(UserPresenceSession $session): string
    {
        return hash('sha256', json_encode($this->snapshot($session), JSON_THROW_ON_ERROR));
    }

    private function newSession(User $owner, array $data, CarbonImmutable $day): UserPresenceSession
    {
        $slots = UserAvailability::where('user_id', $owner->id)->whereDate('date', $data['date'])->orderBy('starts_at')->get();
        $slot = $slots->firstWhere('id', $data['availability_id'] ?? null);
        if (($slots->isNotEmpty() && ! $slot) || (! empty($data['availability_id']) && ! $slot)) {
            throw ValidationException::withMessages(['availability_id' => 'Scegli una fascia programmata di questo operatore per il giorno indicato.']);
        }
        $scheduledStart = $slot ? CarbonImmutable::parse($data['date'].' '.$slot->starts_at)->timestamp : $day->timestamp;
        $scheduledEnd = $slot ? CarbonImmutable::parse($data['date'].' '.$slot->ends_at)->timestamp : $day->timestamp;
        $next = $slot ? $slots->first(fn ($candidate) => $candidate->starts_at >= $slot->ends_at) : null;

        return new UserPresenceSession([
            'user_id' => $owner->id, 'availability_id' => $slot?->id, 'date' => $data['date'],
            'connection_hash' => hash('sha256', random_bytes(32)),
            /** An empty scheduled interval explicitly assigns every available second to time outside a planned slot. */
            'scheduled_start_at' => $scheduledStart, 'scheduled_end_at' => $scheduledEnd,
            'extension_deadline_at' => $next ? CarbonImmutable::parse($data['date'].' '.$next->starts_at)->timestamp : $day->addDay()->timestamp,
        ]);
    }

    private function timestamp(CarbonImmutable $day, string $time, string $field, ?int $original): int
    {
        $local = $day->toDateString().' '.(strlen($time) === 5 ? $time.':00' : $time);
        if ($original !== null && UserPresenceSession::localTime($original)->format('Y-m-d H:i:s') === $local) {
            return $original;
        }
        /** Preserve existing DST instants and reject ambiguous or nonexistent new local times instead of silently shifting them. */
        $wallTime = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $local, 'UTC')->timestamp;
        $transitions = (new DateTimeZone(config('app.timezone')))->getTransitions($day->subDay()->timestamp, $day->addDays(2)->timestamp);
        $matches = [];
        foreach (array_unique(array_column($transitions, 'offset')) as $offset) {
            $candidate = $wallTime - $offset;
            if (UserPresenceSession::localTime($candidate)->format('Y-m-d H:i:s') === $local) {
                $matches[] = $candidate;
            }
        }
        if (count($matches) !== 1) {
            throw ValidationException::withMessages([$field => 'Questo orario è ambiguo o inesistente per il cambio dell’ora. Gli orari già registrati possono essere conservati.']);
        }

        return $matches[0];
    }
}
