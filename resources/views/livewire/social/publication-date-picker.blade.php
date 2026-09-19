<div class="publication-date-picker" x-data @keydown.escape.stop="$wire.set('open', false)">
    <button type="button" class="form-in publication-date-trigger" wire:click="toggle"
        aria-label="Data pubblicazione{{ $selectedDate ? ': '.$selectedDate->format('d/m/Y') : '' }}"
        aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="publication-calendar-{{ $this->getId() }}" @disabled($disabled)>
        {{ $selectedDate ? $selectedDate->format('d/m/Y') : 'Seleziona data' }}
    </button>
    @if($open)
        <div class="publication-calendar" id="publication-calendar-{{ $this->getId() }}" role="group" aria-label="Calendario pubblicazioni del cliente">
            <div class="publication-calendar-heading">
                <button type="button" class="btn btn-g btn-sm" wire:click="moveMonth(-1)" aria-label="Mese precedente">Precedente</button>
                <strong aria-live="polite">{{ ucfirst($monthDate->locale('it')->translatedFormat('F Y')) }}</strong>
                <button type="button" class="btn btn-g btn-sm" wire:click="moveMonth(1)" aria-label="Mese successivo">Successivo</button>
            </div>
            <p class="publication-calendar-help">Post dello stesso cliente, incluse le bozze. Il pallino rosso indica un giorno occupato.</p>
            <div class="publication-calendar-weekdays" aria-hidden="true">
                @foreach(['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'] as $day)
                    <span>{{ $day }}</span>
                @endforeach
            </div>
            @foreach($weeks as $week)
                <div class="publication-calendar-week">
                    <div class="publication-calendar-days">
                        @foreach($week['days'] as $day)
                            @php($date = $day['date']->toDateString())
                            <button type="button" wire:click="selectDate('{{ $date }}')" wire:key="publication-day-{{ $date }}"
                                data-date="{{ $date }}" data-publications="{{ $day['count'] }}"
                                class="publication-calendar-day {{ $day['date']->month === $monthDate->month ? '' : 'is-other-month' }} {{ $value === $date ? 'is-selected' : '' }}"
                                aria-pressed="{{ $value === $date ? 'true' : 'false' }}"
                                @if($day['date']->isToday()) aria-current="date" @endif
                                aria-label="{{ $day['date']->locale('it')->translatedFormat('l j F Y') }}, {{ $day['count'] }} {{ $day['count'] === 1 ? 'pubblicazione' : 'pubblicazioni' }}">
                                <span>{{ $day['date']->day }}</span>
                                <span class="publication-calendar-indicator" aria-hidden="true">
                                    @if($day['count'])<span class="publication-calendar-dot"></span>@endif
                                    @if($day['count'] > 1)<span>{{ $day['count'] }}</span>@endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                    <p class="publication-calendar-week-total">{{ $week['total'] }} {{ $week['total'] === 1 ? 'pubblicazione nella settimana' : 'pubblicazioni nella settimana' }}</p>
                </div>
            @endforeach
            <div class="publication-calendar-actions">
                <button type="button" class="btn btn-g btn-sm" wire:click="selectDate('{{ now()->toDateString() }}')">Oggi</button>
                <button type="button" class="btn btn-g btn-sm" wire:click="selectDate(null)">Rimuovi data</button>
                <button type="button" class="btn btn-g btn-sm" wire:click="toggle">Chiudi</button>
            </div>
        </div>
    @endif
</div>
