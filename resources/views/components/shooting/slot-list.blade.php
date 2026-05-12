<div>
    @if($showWarning && $interactive)
        <div class="shooting-alert-warn">
            <i data-lucide="alert-triangle" class="shooting-alert-icon"></i>
            <span class="shooting-alert-text">Accettando uno slot, tutti gli altri verranno automaticamente rifiutati.</span>
        </div>
    @endif

    <div class="g-cards">
        @foreach($shoot->slots as $slot)
            @php
                $isSelected = $shoot->selected_slot_id === $slot->id;
                $hasSelection = !is_null($shoot->selected_slot_id);
                $isRejected = $slot->status->value === 'rejected' || ($hasSelection && !$isSelected);
                
                $wrapperClass = $isSelected ? 'selected' : ($isRejected ? 'rejected' : '');
            @endphp
            <div class="shooting-slot-wrapper {{ $wrapperClass }}">
                
                @if($isSelected)
                    <div class="shooting-slot-badge-selected">
                        Selezionato
                    </div>
                @endif
                
                <div class="shooting-slot-date">
                    {{ $slot->date->format('d/m/Y') }}
                </div>
                
                <div class="shooting-slot-period">
                    {{ $slot->period->label() }}<br>
                    <span class="shooting-slot-time">{{ $slot->starts_at }} - {{ $slot->ends_at }}</span>
                </div>
                
                <div class="shooting-slot-badges">
                    @php
                        $badgeClass = 'bd';
                        $badgeLabel = $slot->status->label();
                        if ($isSelected || $slot->status->value === 'accepted') {
                            $badgeClass = 'bg'; 
                            $badgeLabel = 'Confermato';
                        }
                        elseif ($isRejected) {
                            $badgeClass = 'bd'; 
                            $badgeLabel = ($hasSelection && !$isSelected) ? 'Scartato automaticamente' : 'Scartato';
                        }
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                </div>
                
                @if($interactive && $slot->status->value === 'proposed')
                    <button wire:click="acceptSlot({{ $slot->id }})" wire:loading.attr="disabled" class="btn btn-p shooting-btn-accept">Accetta questo Slot</button>
                @endif
            </div>
        @endforeach
    </div>

    @if($interactive && $shoot->slots->where('status.value', 'proposed')->count() > 0)
        <div class="shooting-reject-wrap">
            <label class="form-lbl">Note per il rifiuto (opzionale)</label>
            <textarea wire:model="photographerNote" class="form-in shooting-textarea-mb8" rows="2"></textarea>
            <button wire:click="rejectAllSlots" wire:loading.attr="disabled" class="btn btn-outline shooting-btn-reject">Rifiuta Tutti gli Slot</button>
        </div>
    @endif
</div>
