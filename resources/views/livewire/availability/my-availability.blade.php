<div class="availability-page">
    <x-page-header
        eyebrow="Modulo · Operativo"
        title="Le mie disponibilità"
        meta="Scegli un giorno e aggiungi una o più fasce di disponibilità."
    />

    @if($successMessage)
        <div class="flash flash-success" role="status" aria-live="polite">
            {{ $successMessage }}
        </div>
    @endif

    <x-panel title="Settimana" class="availability-week-panel">
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

            <div class="availability-days">
                @foreach($days as $day)
                    @php
                        $dateKey = $day->toDateString();
                        $dayAvailabilities = $availabilitiesByDate->get($dateKey, collect());
                        $canAdd = ! $day->isBefore(today());
                        $isEditorForDay = $editorOpen && $date === $dateKey;
                    @endphp

                    <section
                        class="availability-day {{ $day->isToday() ? 'is-today' : '' }} {{ $dayAvailabilities->isEmpty() ? 'is-empty' : '' }} {{ $isEditorForDay ? 'has-editor' : '' }}"
                        wire:key="availability-day-{{ $dateKey }}"
                        @if($day->isToday()) aria-current="date" @endif
                    >
                        <header class="availability-day-header">
                            <div>
                                <h2>{{ ucfirst($day->locale('it')->isoFormat('dddd')) }}</h2>
                                <time datetime="{{ $dateKey }}">{{ $day->locale('it')->isoFormat('D MMMM') }}</time>
                            </div>

                            @if($canAdd && ! $isEditorForDay)
                                <button
                                    type="button"
                                    wire:click="beginCreate('{{ $dateKey }}')"
                                    class="btn btn-g btn-sm"
                                    aria-label="Aggiungi una fascia per {{ $day->locale('it')->isoFormat('D MMMM') }}"
                                >
                                    <i data-lucide="plus" class="u-icon-sm" aria-hidden="true"></i>
                                    Aggiungi fascia
                                </button>
                            @endif
                        </header>

                        @if($isEditorForDay)
                            <div class="availability-inline-editor" wire:key="availability-editor-{{ $dateKey }}">
                                <div class="availability-editor-header">
                                    <div>
                                        <span class="availability-editor-mode">
                                            {{ $editingId ? 'Modifica fascia' : 'Nuova fascia' }}
                                        </span>
                                        <h3 id="availability-editor-title-{{ $dateKey }}">
                                            {{ ucfirst($day->locale('it')->isoFormat('dddd D MMMM')) }}
                                        </h3>
                                    </div>

                                    <button type="button" wire:click="cancelEdit" class="btn btn-g btn-sm">
                                        Annulla
                                    </button>
                                </div>

                                @error('date')
                                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                @enderror

                                <form
                                    wire:submit="save"
                                    class="availability-inline-form"
                                    aria-labelledby="availability-editor-title-{{ $dateKey }}"
                                    x-data
                                    x-init="$nextTick(() => $refs.availabilityStart.focus())"
                                >
                                    <div class="form-row availability-time-row">
                                        <div class="form-g">
                                            <label for="availability-start-{{ $dateKey }}" class="form-lbl">Dalle</label>
                                            <input
                                                id="availability-start-{{ $dateKey }}"
                                                type="time"
                                                wire:model="startsAt"
                                                x-ref="availabilityStart"
                                                class="form-in @error('startsAt') is-invalid @enderror"
                                                required
                                            >
                                            @error('startsAt')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-g">
                                            <label for="availability-end-{{ $dateKey }}" class="form-lbl">Alle</label>
                                            <input
                                                id="availability-end-{{ $dateKey }}"
                                                type="time"
                                                wire:model="endsAt"
                                                class="form-in @error('endsAt') is-invalid @enderror"
                                                required
                                            >
                                            @error('endsAt')
                                                <span class="invalid-feedback" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>

                                    <p class="availability-form-help">
                                        Puoi inserire più fasce nello stesso giorno. Le fasce non possono sovrapporsi.
                                    </p>

                                    <div class="availability-form-actions">
                                        <button type="submit" class="btn btn-p" wire:loading.attr="disabled" wire:target="save">
                                            <span wire:loading.remove wire:target="save">
                                                {{ $editingId ? 'Salva modifica' : 'Aggiungi disponibilità' }}
                                            </span>
                                            <span wire:loading wire:target="save">Salvataggio…</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif

                        <div class="availability-slot-list">
                            @forelse($dayAvailabilities as $availability)
                                <div
                                    class="availability-slot {{ (int) $editingId === $availability->id ? 'is-editing' : '' }}"
                                    wire:key="availability-slot-{{ $availability->id }}"
                                >
                                    <time
                                        datetime="{{ $dateKey }}T{{ $availability->startsAtForInput() }}"
                                        class="availability-slot-time"
                                    >
                                        {{ $availability->timeRangeLabel() }}
                                    </time>

                                    <div class="availability-slot-actions">
                                        @if($canAdd && (int) $editingId !== $availability->id)
                                            <button
                                                type="button"
                                                wire:click="editAvailability({{ $availability->id }})"
                                                class="btn btn-g btn-sm"
                                                aria-label="Modifica la fascia {{ $availability->timeRangeLabel() }}"
                                            >
                                                Modifica
                                            </button>
                                        @endif

                                        <x-confirm-modal
                                            title="Elimina disponibilità"
                                            message="Vuoi eliminare la fascia {{ $availability->timeRangeLabel() }} del {{ $day->locale('it')->isoFormat('D MMMM YYYY') }}?"
                                            confirmText="Elimina"
                                            confirmMethod="deleteAvailability({{ $availability->id }})"
                                            confirmClass="btn btn-p btn-danger"
                                            icon="trash-2"
                                            variant="danger"
                                        >
                                            <button
                                                type="button"
                                                class="btn btn-g btn-sm"
                                                aria-label="Elimina la fascia {{ $availability->timeRangeLabel() }}"
                                            >
                                                Elimina
                                            </button>
                                        </x-confirm-modal>
                                    </div>
                                </div>
                            @empty
                                @unless($isEditorForDay)
                                    <p class="availability-not-set">Disponibilità non indicata.</p>
                                @endunless
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
    </x-panel>
</div>
