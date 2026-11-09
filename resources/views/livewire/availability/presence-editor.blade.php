<section class="presence-editor" aria-labelledby="presence-editor-title" wire:key="presence-editor-{{ $editorVersion }}">
    <div class="availability-editor-header">
        <h3 id="presence-editor-title">{{ $editingPresenceId ? 'Correggi presenza' : 'Inserisci presenza' }}</h3>
        <button type="button" class="btn btn-g btn-sm" wire:click="closeEditor" wire:loading.attr="disabled">Annulla</button>
    </div>
    <form wire:submit="savePresence" x-data x-init="$nextTick(() => $el.querySelector('select:not(:disabled), input:not(:disabled), textarea')?.focus())">
        @if($errors->any())
            <div class="presence-control-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if($editingSession)
            <p><strong>{{ $editingSession->user->name }}</strong> · {{ $editingSession->date->format('d/m/Y') }} · {{ $summary->historyRow($editingSession)['slot'] }}</p>
        @else
            <div class="presence-editor-fields">
                <div class="form-g">
                    <label class="form-lbl" for="presence-editor-user">Operatore</label>
                    <select id="presence-editor-user" class="form-in" wire:model.live="form.user_id" required @disabled($userId !== null) aria-invalid="{{ $errors->has('form.user_id') ? 'true' : 'false' }}">
                        <option value="">Scegli l’operatore</option>
                        @foreach($editorUsers as $operator)<option value="{{ $operator->id }}">{{ $operator->name }}</option>@endforeach
                    </select>
                </div>
                <div class="form-g">
                    <label class="form-lbl" for="presence-editor-date">Giorno</label>
                    <input id="presence-editor-date" class="form-in" type="date" wire:model.live="form.date" required
                        min="{{ $from->toDateString() }}" max="{{ min(today()->toDateString(), $from->endOfWeek()->toDateString()) }}" aria-invalid="{{ $errors->has('form.date') ? 'true' : 'false' }}">
                </div>
                <div class="form-g">
                    <label class="form-lbl" for="presence-editor-slot">Fascia programmata</label>
                    <select id="presence-editor-slot" class="form-in" wire:model="form.availability_id" @required($editorSlots->isNotEmpty()) aria-invalid="{{ $errors->has('form.availability_id') ? 'true' : 'false' }}">
                        <option value="">{{ $editorSlots->isEmpty() ? 'Senza fascia programmata' : 'Scegli la fascia' }}</option>
                        @foreach($editorSlots as $slot)<option value="{{ $slot->id }}">{{ $slot->timeRangeLabel() }}</option>@endforeach
                    </select>
                </div>
            </div>
        @endif
        @if(($editingSession && $editingSession->scheduled_start_at === $editingSession->scheduled_end_at) || (! $editingSession && $editorSlots->isEmpty()))
            <p class="availability-form-help">Senza fascia programmata, tutto il tempo disponibile viene conteggiato come oltre fascia.</p>
        @endif
        <p class="availability-form-help">Registra gli intervalli di disponibilità e le pause in ordine. I periodi tra due intervalli sono esclusi dai totali. Gli orari sono in {{ config('app.timezone') }} e le 00:00 in uscita indicano la mezzanotte successiva.</p>
        <div class="presence-editor-intervals">
            @foreach($form['intervals'] ?? [] as $index => $interval)
                <fieldset class="presence-editor-interval" wire:key="presence-interval-{{ $editorVersion }}-{{ $index }}">
                    <legend class="sr-only">Intervallo {{ $index + 1 }}</legend>
                    <div class="form-g">
                        <label class="form-lbl" for="presence-kind-{{ $index }}">Tipo</label>
                        <select id="presence-kind-{{ $index }}" wire:model="form.intervals.{{ $index }}.kind" class="form-in" required>
                            <option value="available">Disponibile</option><option value="paused">Pausa</option>
                        </select>
                    </div>
                    <div class="form-g">
                        <label class="form-lbl" for="presence-start-{{ $index }}">Ingresso</label>
                        <input id="presence-start-{{ $index }}" type="time" step="1" wire:model="form.intervals.{{ $index }}.starts_at" class="form-in" required aria-invalid="{{ $errors->has('form.intervals.'.$index.'.starts_at') ? 'true' : 'false' }}">
                    </div>
                    <div class="form-g">
                        <label class="form-lbl" for="presence-end-{{ $index }}">Uscita</label>
                        <input id="presence-end-{{ $index }}" type="time" step="1" wire:model="form.intervals.{{ $index }}.ends_at" class="form-in" required aria-invalid="{{ $errors->has('form.intervals.'.$index.'.ends_at') ? 'true' : 'false' }}">
                    </div>
                    <button type="button" wire:click="removeInterval({{ $index }})" class="btn btn-g btn-sm" aria-label="Rimuovi intervallo {{ $index + 1 }}" wire:loading.attr="disabled">Rimuovi</button>
                </fieldset>
            @endforeach
        </div>
        <button type="button" wire:click="addInterval" class="btn btn-g btn-sm" wire:loading.attr="disabled" @disabled(count($form['intervals'] ?? []) >= 60)>Aggiungi intervallo</button>
        <div class="form-g presence-editor-reason">
            <label class="form-lbl" for="presence-editor-reason">Motivo della {{ $editingPresenceId ? 'correzione' : 'registrazione' }}</label>
            <textarea id="presence-editor-reason" wire:model="form.reason" class="form-in" rows="3" required minlength="3" maxlength="1000" aria-invalid="{{ $errors->has('form.reason') ? 'true' : 'false' }}"></textarea>
        </div>
        <div class="availability-form-actions">
            <button type="submit" class="btn btn-p" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="savePresence">{{ $editingPresenceId ? 'Salva correzione' : 'Registra presenza' }}</span>
                <span wire:loading wire:target="savePresence">Salvataggio…</span>
            </button>
        </div>
    </form>
    @if($corrections->isNotEmpty())
        <details class="presence-corrections">
            <summary>Storico delle modifiche</summary>
            @foreach($corrections as $correction)
                <article>
                    <h4>{{ $correction->created_at->format('d/m/Y H:i:s') }} · {{ $correction->user?->name ?? 'Utente rimosso' }}</h4>
                    <p>{{ $correction->description }}</p>
                    <div class="presence-correction-values">
                        @foreach(['Prima' => $correction->old_values, 'Dopo' => $correction->new_values] as $label => $values)
                            <div>
                                <strong>{{ $label }}</strong>
                                @if($values)
                                    <ul>@foreach($values['intervals'] as $recorded)
                                        <li>{{ $recorded['kind'] === 'paused' ? 'Pausa' : 'Disponibile' }}
                                            {{ \App\Models\UserPresenceSession::localTime($recorded['started_at'])->format('H:i:s') }}–{{ \App\Models\UserPresenceSession::localTime($recorded['ended_at'] ?? $values['last_seen_at'])->format('H:i:s') }}
                                            @if($recorded['ended_at'] === null) (ultimo segnale) @endif
                                        </li>
                                    @endforeach</ul>
                                @else
                                    <p>Presenza aggiunta manualmente.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </details>
    @endif
</section>
