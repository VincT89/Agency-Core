<div>
    <x-page-header 
        eyebrow="Amministrazione" 
        meta="Gestione delle spese e dei costi operativi">
        <x-slot:title><strong>Spese</strong> Generali</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('expenses.create') }}" wire:navigate class="btn btn-p">+ Nuova Spesa</a>
        </x-slot:actions>
    </x-page-header>

    <div class="filter-bar">
        <div class="pills u-m-0">
            <button wire:click="$set('status', '')" class="pill {{ !$status ? 'on' : '' }}">Tutte</button>
            <button wire:click="$set('status', 'pending')" class="pill {{ $status === 'pending' ? 'on' : '' }}">Da Pagare</button>
            <button wire:click="$set('status', 'paid')" class="pill {{ $status === 'paid' ? 'on' : '' }}">Pagate</button>
            <button wire:click="$set('status', 'cancelled')" class="pill {{ $status === 'cancelled' ? 'on' : '' }}">Annullate</button>
        </div>

        <div class="u-flex u-gap-sm filter-form u-ml-auto">
            <select wire:model.live="expenseable_type" class="form-in form-in-sm u-w-150">
                <option value="">Tutti i collegamenti</option>
                <option value="client">Clienti</option>
                <option value="project">Progetti</option>
                <option value="ticket">Ticket</option>
                <option value="task">Task</option>
            </select>

            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cerca spesa..." class="form-in form-in-sm filter-search">
            @if($search || $status || $expenseable_type)
                <button wire:click="$set('search', ''); $set('status', ''); $set('expenseable_type', '');" class="btn btn-g btn-sm">Reset</button>
            @endif
        </div>
    </div>

    <x-panel>
        <div class="t-table-wrap">
            <table class="t-table u-w-full">
                <thead>
                    <tr>
                        <th>Spesa</th>
                        <th>Importo</th>
                        <th>Fornitore</th>
                        <th>Scadenza</th>
                        <th>Collegamento</th>
                        <th>Stato</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $expense)
                    <tr x-data @click="window.Livewire.navigate('{{ route('expenses.show', $expense) }}')" class="u-cursor-pointer hover-bg">
                        <td class="name-col">
                            {{ $expense->title }}
                            @if($expense->category)
                                <div class="u-text-meta u-text-muted u-mt-xs">{{ $expense->category }}</div>
                            @endif
                        </td>
                        <td class="mono-col">
                            &euro; {{ number_format($expense->amount, 2, ',', '.') }}
                        </td>
                        <td>
                            {{ $expense->supplier ?? '—' }}
                        </td>
                        <td class="mono-col">
                            @if($expense->due_date)
                                <span class="{{ $expense->is_overdue ? 'u-text-red u-text-strong' : '' }}">
                                    {{ $expense->due_date->format('d/m/Y') }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($expense->expenseable)
                                <div class="badge-mini u-bg-accent"></div>
                                <span class="u-text-label u-text-normal-case">
                                    {{ class_basename($expense->expenseable_type) }}: {{ $expense->expenseable->title ?? $expense->expenseable->name ?? $expense->expenseable->code ?? 'N/A' }}
                                </span>
                            @else
                                <span class="u-text-meta u-text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($expense->status === 'paid')
                                <span class="badge badge-success">Pagata</span>
                            @elseif($expense->status === 'cancelled')
                                <span class="badge badge-gray">Annullata</span>
                            @elseif($expense->is_overdue)
                                <span class="badge badge-danger">Scaduta</span>
                            @else
                                <span class="badge badge-warning">Da Pagare</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('expenses.edit', $expense) }}" wire:navigate class="btn-icon" @click.stop>✎</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="u-empty-state">Nessuna spesa trovata</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($expenses->hasPages())
        <div class="u-p-md u-border-t">
            {{ $expenses->links() }}
        </div>
        @endif
    </x-panel>
</div>
