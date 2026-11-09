@php($snapshot = $log->new_values ?? $log->old_values ?? [])
<details class="presence-audit-details">
    <summary>Dettagli della presenza</summary>
    <p><strong>{{ $snapshot['user_name'] ?? $presenceUsers[$snapshot['user_id'] ?? 0] ?? 'Utente rimosso' }}</strong>
        @if(isset($snapshot['date'])) · {{ \Carbon\CarbonImmutable::parse($snapshot['date'])->format('d/m/Y') }} @endif
    </p>
    <p>{{ $log->description }}</p>
    <div class="presence-correction-values">
        @foreach(['Prima' => $log->old_values, 'Dopo' => $log->new_values] as $label => $values)
            <div>
                <strong>{{ $label }}</strong>
                @if($values)
                    @if(isset($values['scheduled_start_at'], $values['scheduled_end_at']))
                        <p>
                            @if($values['scheduled_start_at'] === $values['scheduled_end_at'])
                                Senza fascia programmata
                            @else
                                Fascia {{ \App\Models\UserPresenceSession::localTime($values['scheduled_start_at'])->format('H:i') }}–{{ \App\Models\UserPresenceSession::localTime($values['scheduled_end_at'])->format('H:i') }}
                            @endif
                        </p>
                    @endif
                    <ul>@foreach($values['intervals'] ?? [] as $interval)
                        <li>{{ $interval['kind'] === 'paused' ? 'Pausa' : 'Disponibile' }}
                            {{ \App\Models\UserPresenceSession::localTime($interval['started_at'])->format('H:i:s') }}–{{ \App\Models\UserPresenceSession::localTime($interval['ended_at'] ?? $values['confirmed_until'] ?? $values['last_seen_at'])->format('H:i:s') }}
                            @if($interval['ended_at'] === null) (ultimo segnale) @endif
                        </li>
                    @endforeach</ul>
                    @if(isset($values['available_seconds'], $values['paused_seconds'], $values['extra_seconds']))
                        <p>Disponibilità {{ \App\Models\UserPresenceSession::durationLabel($values['available_seconds']) }}.
                            Pause {{ \App\Models\UserPresenceSession::durationLabel($values['paused_seconds']) }}.
                            Oltre fascia {{ \App\Models\UserPresenceSession::durationLabel($values['extra_seconds']) }}.
                        </p>
                    @endif
                @else
                    <p>{{ $log->action === 'presence_deleted' ? 'Presenza eliminata.' : 'Nessuna presenza precedente.' }}</p>
                @endif
            </div>
        @endforeach
    </div>
</details>
