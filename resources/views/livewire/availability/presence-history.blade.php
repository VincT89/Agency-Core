<div wire:poll.20s>
    <x-panel title="Storico presenze">
        <div class="presence-history-wrap" role="region" aria-label="Storico delle presenze confermate" tabindex="0">
            <table class="t-table presence-history-table">
                <thead><tr>
                    @if($team)<th scope="col">Operatore</th>@endif
                    <th scope="col">Data e fascia</th><th scope="col">Ingresso</th><th scope="col">Uscita</th>
                    <th scope="col">Disponibilità</th><th scope="col">Pause</th><th scope="col">Oltre fascia</th><th scope="col">Stato</th>
                </tr></thead>
                <tbody>
                    @forelse($sessions as $session)
                        @php($row = $summary->historyRow($session))
                        <tr wire:key="presence-history-{{ $session->id }}">
                            @if($team)<th scope="row">{{ $session->user->name }}</th>@endif
                            <td><strong>{{ $session->date->format('d/m/Y') }}</strong><br>{{ $row['slot'] }}</td>
                            <td>{{ $row['started'] }}</td>
                            <td>{{ $row['ended'] ?? 'In corso' }}@if($row['estimated_end'])<br><small>Ultimo segnale ricevuto</small>@endif</td>
                            <td>{{ $row['available'] }}</td><td>{{ $row['paused'] }}</td>
                            <td>{{ $row['extra'] }}@if($row['extra_confirmed'])<br><small>Confermato alle {{ $row['extra_confirmed'] }}</small>@endif</td>
                            <td>
                                <span class="presence-status" data-state="{{ $row['state'] }}"><span class="presence-dot" aria-hidden="true"></span>{{ $row['reason'] }}</span>
                                <details class="presence-intervals"><summary>Intervalli</summary>
                                    <ul>@foreach($session->intervals as $interval)
                                        <li>{{ $interval->kind === 'paused' ? 'Pausa' : 'Disponibile' }}:
                                            {{ \App\Models\UserPresenceSession::localTime($interval->started_at)->format('H:i') }}–{{ \App\Models\UserPresenceSession::localTime(min($interval->ended_at ?? $session->confirmedUntil(), $session->confirmedUntil()))->format('H:i') }}
                                        </li>
                                    @endforeach</ul>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $team ? 8 : 7 }}" class="presence-empty">Nessuna presenza confermata in questa settimana.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $sessions->links() }}
    </x-panel>
</div>
