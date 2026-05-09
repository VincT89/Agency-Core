<div>
    <div class="u-mb-lg u-flex-end">
        <a href="{{ route('expenses.index') }}" wire:navigate class="btn btn-g u-flex-center u-gap-xs">
            <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna alla lista
        </a>
    </div>

    <x-page-header 
        eyebrow="Dettaglio Spesa #{{ $expense->id }}" 
        meta="{{ $expense->category ?? 'Spesa Operativa' }}">
        <x-slot:title>
            <strong>{{ $expense->title }}</strong>
        </x-slot:title>
        <x-slot:actions>
            @if($expense->status === 'paid')
                <x-badge status="paid" label="Pagata" />
            @elseif($expense->status === 'cancelled')
                <x-badge status="cancelled" label="Annullata" />
            @elseif($expense->is_overdue)
                <x-badge status="overdue" label="Scaduta" />
            @else
                <x-badge status="pending" label="Da Pagare" />
            @endif

            @if($expense->status === 'pending')
                <button type="button" wire:click="markAsPaid" class="btn btn-p">
                    Segna Pagata
                </button>
            @endif

            <a href="{{ route('expenses.edit', $expense) }}" wire:navigate class="btn btn-g">
                Modifica
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="g-2col-main">
        
        {{-- COLONNA SINISTRA (Info & Dettagli) --}}
        <div>
            <x-panel title="Dettagli Spesa" padded>
                <div class="ticket-badges">
                    <span class="badge badge-gray">&euro; {{ number_format($expense->amount, 2, ',', '.') }}</span>
                    @if($expense->expense_date)
                        <span class="badge badge-gray">Data: {{ $expense->expense_date->format('d/m/Y') }}</span>
                    @endif
                    @if($expense->status === 'paid')
                        <span class="badge badge-success">Pagata il {{ $expense->paid_at?->format('d/m/Y') ?? 'N/D' }}</span>
                    @endif
                    @if($expense->due_date)
                        <span class="badge {{ $expense->is_overdue ? 'badge-danger' : 'badge-gray' }}">Scadenza: {{ $expense->due_date->format('d/m/Y') }}</span>
                    @endif
                    @if($expense->supplier)
                        <span class="badge badge-gray">Fornitore: {{ $expense->supplier }}</span>
                    @endif
                </div>

                @if($expense->description)
                <div class="ticket-description">{{ $expense->description }}</div>
                @else
                <div class="ticket-description-empty">Nessuna descrizione fornita.</div>
                @endif
            </x-panel>

            <div class="u-mt-md">
                <x-panel title="Ricevute e Allegati" dot="var(--blue)" padded>
                    <livewire:shared.attachment-manager :model="$expense" />
                </x-panel>
            </div>
        </div>

        {{-- COLONNA DESTRA (Sidebar) --}}
        <div class="g-2col-side">
            
            {{-- Imputazione Costo --}}
            <x-panel title="Imputazione Costo" dot="var(--orange)" padded>
                @if($expense->expenseable)
                    <div class="u-flex u-gap-sm u-items-center u-mb-md">
                        <div class="badge-mini u-bg-accent"></div>
                        <span class="u-text-label u-text-muted">{{ class_basename($expense->expenseable_type) }}</span>
                    </div>
                    <div class="u-text-body u-text-strong u-mb-sm">
                        {{ $expense->expenseable->title ?? $expense->expenseable->name ?? $expense->expenseable->code ?? 'N/A' }}
                    </div>
                    <a href="{{ route(strtolower(class_basename($expense->expenseable_type)) . 's.show', $expense->expenseable) }}" class="t-link u-text-meta">
                        Vai all'elemento <i data-lucide="arrow-right" class="u-icon-xs u-ml-xs u-inline-middle"></i>
                    </a>
                @else
                    <div class="u-text-muted u-text-meta u-text-center u-py-md">
                        Nessun collegamento.<br>Costo operativo generale.
                    </div>
                @endif
            </x-panel>

            {{-- Note interne --}}
            @if($expense->notes)
            <div class="u-mt-md">
                <x-panel title="Note Interne" dot="var(--gray)" padded>
                    <div class="u-text-meta u-text-muted u-whitespace-pre-wrap">{{ $expense->notes }}</div>
                </x-panel>
            </div>
            @endif

            {{-- Azioni Rapide --}}
            <div class="u-mt-md">
                <x-panel title="Azioni Rapide" padded>
                    <div class="u-flex-column u-gap-sm">
                        @if($expense->status === 'paid' || $expense->status === 'cancelled')
                            <button type="button" wire:click="markAsPending" class="btn btn-g u-w-full u-justify-center">
                                Riporta a Da Pagare
                            </button>
                        @endif

                        @if($expense->status !== 'cancelled')
                            <button type="button" wire:click="markAsCancelled" class="btn btn-g btn-danger u-w-full u-justify-center">
                                Annulla Spesa
                            </button>
                        @endif
                    </div>
                </x-panel>
            </div>

        </div>
    </div>
</div>
