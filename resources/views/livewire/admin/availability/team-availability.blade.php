<div class="availability-page">
    <x-page-header
        eyebrow="Modulo · Admin"
        title="Disponibilità team"
        meta="Consulta le fasce dichiarate dagli utenti. L’assenza di fasce indica che la disponibilità non è stata comunicata."
    />

    <x-panel class="availability-controls-panel" padded>
        <div class="availability-admin-controls">
            <div class="availability-week-toolbar" aria-label="Navigazione settimana">
                <button type="button" wire:click="previousWeek" class="btn btn-g btn-sm" aria-label="Settimana precedente">
                    <i data-lucide="chevron-left" class="u-icon-sm" aria-hidden="true"></i>
                    <span>Precedente</span>
                </button>

                <div class="availability-week-range" aria-live="polite">
                    <strong>{{ $weekStartDate->locale('it')->isoFormat('D MMM') }}</strong>
                    <span>–</span>
                    <strong>{{ $weekEndDate->locale('it')->isoFormat('D MMM YYYY') }}</strong>
                    <button type="button" wire:click="currentWeek" class="availability-today-link">Vai a oggi</button>
                </div>

                <button type="button" wire:click="nextWeek" class="btn btn-g btn-sm" aria-label="Settimana successiva">
                    <span>Successiva</span>
                    <i data-lucide="chevron-right" class="u-icon-sm" aria-hidden="true"></i>
                </button>
            </div>

            <div class="form-g availability-user-filter">
                <label for="availability-user-filter" class="form-lbl">Utente</label>
                <select id="availability-user-filter" wire:model.live="selectedUserId" class="form-in">
                    <option value="">Tutti gli utenti</option>
                    @foreach($userOptions as $userOption)
                        <option value="{{ $userOption->id }}">
                            {{ $userOption->name }}{{ $userOption->status === 'inactive' ? ' — inattivo' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-panel>

    <x-panel title="Riepilogo settimanale" class="availability-summary-panel">
        <div class="availability-team-table-wrap">
            <table class="t-table availability-team-table">
                <caption class="sr-only">
                    Disponibilità degli utenti dal {{ $weekStartDate->format('d/m/Y') }} al {{ $weekEndDate->format('d/m/Y') }}
                </caption>
                <thead>
                    <tr>
                        <th scope="col">Utente</th>
                        @foreach($days as $day)
                            <th
                                scope="col"
                                class="{{ $day->isToday() ? 'is-today' : '' }}"
                                @if($day->isToday()) aria-current="date" @endif
                            >
                                <span>{{ ucfirst($day->locale('it')->isoFormat('ddd')) }}</span>
                                <time datetime="{{ $day->toDateString() }}">{{ $day->format('d/m') }}</time>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        @php
                            $userAvailabilities = $user->availabilities->groupBy(
                                fn ($availability) => $availability->date->toDateString()
                            );
                        @endphp
                        <tr wire:key="team-availability-user-{{ $user->id }}">
                            <th scope="row" class="availability-team-user">
                                <strong>{{ $user->name }}</strong>
                                <span>{{ $user->role->label() }}</span>
                                @if($user->status === 'inactive')
                                    <span>Account inattivo</span>
                                @endif
                            </th>
                            @foreach($days as $day)
                                @php($daySlots = $userAvailabilities->get($day->toDateString(), collect()))
                                <td class="availability-team-day {{ $day->isToday() ? 'is-today' : '' }}">
                                    @forelse($daySlots as $slot)
                                        <time datetime="{{ $day->toDateString() }}T{{ $slot->startsAtForInput() }}">
                                            {{ $slot->timeRangeLabel() }}
                                        </time>
                                    @empty
                                        <span class="availability-not-set">Non indicata</span>
                                    @endforelse
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="u-text-center u-text-muted u-p-xl">
                                Nessun utente corrisponde al filtro selezionato.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>
</div>
