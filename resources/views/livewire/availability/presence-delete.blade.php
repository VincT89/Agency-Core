@php($deletingRow = $summary->historyRow($editingSession))
<section class="presence-editor presence-delete" aria-labelledby="presence-delete-title" wire:key="presence-delete-{{ $editorVersion }}">
    <div class="availability-editor-header">
        <h3 id="presence-delete-title">Elimina presenza</h3>
        <button type="button" class="btn btn-g btn-sm" wire:click="closeEditor" wire:loading.attr="disabled">Annulla</button>
    </div>
    <p><strong>{{ $editingSession->user->name }}</strong> · {{ $editingSession->date->format('d/m/Y') }} · {{ $deletingRow['slot'] }}</p>
    <p>Ingresso alle {{ \App\Models\UserPresenceSession::localTime($editingSession->started_at)->format('H:i:s') }}.
        @if($editingSession->effectiveEnd(now()->timestamp) !== null)
            Uscita alle {{ \App\Models\UserPresenceSession::localTime($editingSession->effectiveEnd(now()->timestamp))->format('H:i:s') }}.
        @endif
    </p>
    @if($deletingRow['ended'] === null)
        <p id="presence-delete-active" class="presence-control-error" role="note">Questa presenza è ancora in corso. Confermando l’eliminazione interrompi il suo conteggio. L’operatore dovrà confermare una nuova disponibilità per ripartire.</p>
    @endif
    <p id="presence-delete-impact">La presenza e i suoi intervalli verranno eliminati dai totali. Il registro attività conserverà autore, motivo e dati precedenti.</p>
    <form wire:submit="deletePresence" x-data x-init="$nextTick(() => $refs.deleteReason.focus())">
        @if($errors->any())
            <div class="presence-control-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="form-g">
            <label class="form-lbl" for="presence-delete-reason">Motivo della cancellazione</label>
            <textarea id="presence-delete-reason" x-ref="deleteReason" wire:model="form.reason" class="form-in" rows="3" required minlength="3" maxlength="1000" aria-invalid="{{ $errors->has('form.reason') ? 'true' : 'false' }}" aria-describedby="presence-delete-impact{{ $deletingRow['ended'] === null ? ' presence-delete-active' : '' }}"></textarea>
        </div>
        <div class="availability-form-actions">
            <button type="submit" class="btn btn-p" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="deletePresence">Conferma eliminazione</span>
                <span wire:loading wire:target="deletePresence">Eliminazione…</span>
            </button>
        </div>
    </form>
</section>
