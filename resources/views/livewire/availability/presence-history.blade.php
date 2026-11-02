<div wire:poll.20s>
    <x-panel title="Storico presenze">
        <div class="presence-history-wrap" role="region" aria-label="Storico delle presenze confermate" tabindex="0">
            <table class="t-table presence-history-table">
                <caption class="sr-only">Totali giornalieri per operatore, con dettaglio delle singole fasce.</caption>
                <thead><tr>
                    @if($team)<th scope="col">Operatore</th>@endif
                    <th scope="col">Giorno</th><th scope="col">Primo ingresso</th><th scope="col">Ultima uscita</th>
                    <th scope="col">Disponibilità totale</th><th scope="col">Pause</th><th scope="col">Oltre fascia</th><th scope="col">Stato</th>
                </tr></thead>
                @forelse($days as $day)
                    <tbody wire:key="presence-day-{{ $day['key'] }}" x-data="{ expanded: false }">
                        <tr class="presence-day-row">
                            @if($team)<th scope="row">{{ $day['name'] }}</th>@endif
                            <td>
                                <time datetime="{{ $day['date']->toDateString() }}"><strong>{{ $day['date']->format('d/m/Y') }}</strong></time>
                                <button type="button" class="presence-day-toggle" @click="expanded = !expanded"
                                    :aria-expanded="expanded.toString()" aria-controls="presence-day-details-{{ $day['key'] }}"
                                    aria-label="Dettaglio fasce del {{ $day['date']->format('d/m/Y') }} per {{ $day['name'] }}">
                                    {{ $day['slot_count'] }} {{ $day['slot_count'] === 1 ? 'fascia' : 'fasce' }}
                                </button>
                            </td>
                            <td>{{ $day['started'] }}</td>
                            <td>{{ $day['ended'] ?? 'In corso' }}@if($day['estimated_end'])<br><small>Ultimo segnale ricevuto</small>@endif</td>
                            <td class="presence-day-total"><strong>{{ $day['available'] }}</strong></td>
                            <td>{{ $day['paused'] }}</td><td>{{ $day['extra'] }}</td>
                            <td><span class="presence-status" data-state="{{ $day['state'] }}"><span class="presence-dot" aria-hidden="true"></span>{{ $day['reason'] }}</span></td>
                        </tr>
                        <tr id="presence-day-details-{{ $day['key'] }}" class="presence-day-detail" x-show="expanded" x-cloak>
                            <td colspan="{{ $team ? 8 : 7 }}">
                                <table class="presence-slots-table">
                                    <caption>Fasce del {{ $day['date']->format('d/m/Y') }} per {{ $day['name'] }}</caption>
                                    <thead><tr>
                                        <th scope="col">Fascia</th><th scope="col">Ingresso</th><th scope="col">Uscita</th>
                                        <th scope="col">Disponibilità</th><th scope="col">Pause</th><th scope="col">Oltre fascia</th><th scope="col">Stato e intervalli</th>
                                    </tr></thead>
                                    <tbody>
                                        @foreach($day['sessions'] as $session)
                                            @php($row = $summary->historyRow($session))
                                            <tr wire:key="presence-history-{{ $session->id }}">
                                                <th scope="row">{{ $row['slot'] }}</th>
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
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody><tr><td colspan="{{ $team ? 8 : 7 }}" class="presence-empty">Nessuna presenza confermata in questa settimana.</td></tr></tbody>
                @endforelse
            </table>
        </div>
        {{ $days->links() }}
    </x-panel>
</div>
