<div x-data @note-added.window="setTimeout(() => { let id = $event.detail.id || ($event.detail[0] && $event.detail[0].id) || $event.detail; document.getElementById('note-ta-' + id)?.focus() }, 100)">
    <x-page-header 
        eyebrow="Operatività" 
        meta="Organizza note, checklist e post script per giornata">
        <x-slot:title>Il mio Blocco Note</x-slot:title>
    </x-page-header>

    <div class="notes-container">
        
        {{-- Date Navigator --}}
        <div class="notes-header">
            <button type="button" wire:click="previousDay" class="notes-nav-btn">
                <i data-lucide="chevron-left" class="u-icon-md"></i>
            </button>
            
            <div class="notes-date-display">
                <div class="notes-date-title">
                    {{ \Carbon\Carbon::parse($currentDate)->isToday() ? 'Oggi' : \Carbon\Carbon::parse($currentDate)->locale('it')->isoFormat('D MMMM YYYY') }}
                </div>
                <div class="notes-date-subtitle">
                    {{ \Carbon\Carbon::parse($currentDate)->locale('it')->isoFormat('dddd') }}
                </div>
            </div>

            <button type="button" wire:click="nextDay" class="notes-nav-btn">
                <i data-lucide="chevron-right" class="u-icon-md"></i>
            </button>
        </div>

        <div class="notes-grid">
            @if($note && $note->entries->count() > 0)
                @foreach($note->entries as $entry)
                    <div class="note-card">
                        <div class="note-header-actions">
                            <div class="note-badge">Nota #{{ $loop->iteration }}</div>
                            <x-confirm-modal
                                title="Elimina Nota"
                                message="Sei sicuro di voler eliminare questa nota?"
                                confirmText="Elimina"
                                confirmMethod="deleteEntry({{ $entry->id }})"
                                confirmClass="btn btn-p btn-danger"
                                icon="trash-2"
                                variant="danger"
                            >
                                <button type="button" class="btn-ghost-danger btn-xs">
                                    <i data-lucide="trash-2" class="u-icon-sm"></i>
                                </button>
                            </x-confirm-modal>
                        </div>
                        
                        {{-- Text Content --}}
                        <textarea id="note-ta-{{ $entry->id }}" wire:model.live.debounce.1000ms="entryContents.{{ $entry->id }}" class="note-textarea" placeholder="Scrivi i tuoi appunti qui..."></textarea>
                        
                        {{-- Checklist Items --}}
                        <div class="note-checklist">
                            @if($entry->checklistItems->count() > 0)
                                @foreach($entry->checklistItems as $item)
                                    <div class="note-checklist-item">
                                        <input type="checkbox" wire:click="toggleChecklistItem({{ $item->id }})" {{ $item->is_completed ? 'checked' : '' }} class="note-check-box">
                                        <div class="note-check-label {{ $item->is_completed ? 'completed' : '' }}">
                                            {{ $item->label }}
                                        </div>
                                        <button type="button" wire:click="deleteChecklistItem({{ $item->id }})" class="btn-ghost-danger btn-xs note-delete-muted">
                                            <i data-lucide="x" class="u-icon-sm"></i>
                                        </button>
                                    </div>
                                @endforeach
                            @endif
                            
                            <div class="note-check-input-wrap">
                                <i data-lucide="plus" class="u-icon-sm note-check-add-icon"></i>
                                <input type="text" wire:model="newChecklistLabels.{{ $entry->id }}" wire:keydown.enter="addChecklistItem({{ $entry->id }})" class="note-check-input" placeholder="Aggiungi task e premi invio...">
                            </div>
                        </div>

                        {{-- Post Script --}}
                        <div class="note-ps-wrap">
                            <i data-lucide="message-square" class="u-icon-sm note-ps-icon"></i>
                            <input type="text" wire:model.live.debounce.1000ms="entryPostScripts.{{ $entry->id }}" class="note-ps-input" placeholder="P.S. (Spazio opzionale per note a margine)">
                        </div>
                    </div>
                @endforeach
            @endif

            {{-- Empty State or Add Button --}}
            @if(!$note || $note->entries->count() === 0)
                <div class="notes-empty-state">
                    <i data-lucide="book-open" class="note-empty-icon"></i>
                    <h3>Foglio Bianco</h3>
                    <p>Nessuna nota per {{ \Carbon\Carbon::parse($currentDate)->isToday() ? 'oggi' : 'questa giornata' }}. Inizia a scrivere i tuoi appunti o crea una lista di task.</p>
                </div>
            @endif

            {{-- Add Note Card --}}
            <button type="button" wire:click="addEntry" class="add-note-card">
                <i data-lucide="plus-circle" class="note-add-icon"></i>
                <span class="note-add-label">Nuovo Post-it</span>
            </button>
        </div>
    </div>
</div>
