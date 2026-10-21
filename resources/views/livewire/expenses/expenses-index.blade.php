<div>
    <x-page-header 
        eyebrow="Amministrazione" 
        meta="Gestione delle spese e dei costi operativi">
        <x-slot:title><strong>Spese</strong> Generali</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('expenses.create') }}" wire:navigate class="btn btn-p">+ Nuova Spesa</a>
        </x-slot:actions>
    </x-page-header>

    @include('expenses.partials.navigation')
    @if($recurrence_id)
        <p class="u-mb-md">Stai visualizzando le scadenze di una ricorrenza. <a href="{{ route('expenses.index') }}">Mostra tutte le spese</a></p>
    @endif

    <form method="GET" action="{{ route('expenses.index') }}" class="finance-report-filters">
        @if($category_id)<input type="hidden" name="category_id" value="{{ $category_id }}">@endif
        @if($subcategory_id)<input type="hidden" name="subcategory_id" value="{{ $subcategory_id }}">@endif
        @if($recurrence_id)<input type="hidden" name="recurrence_id" value="{{ $recurrence_id }}">@endif
        <div class="finance-filter-grid">
            <x-form-group label="Cerca spesa, fornitore o categoria" name="search"><input id="field-search" type="search" name="search" class="form-in" value="{{ $search }}" maxlength="100"></x-form-group>
            <x-form-group label="Stato" name="status"><select id="field-status" name="status" class="form-sel">@foreach(['' => 'Tutte', 'pending' => 'Da pagare', 'paid' => 'Pagate', 'cancelled' => 'Annullate'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Data spesa dal" name="from"><input id="field-from" type="date" name="from" class="form-in" value="{{ $from }}"></x-form-group>
            <x-form-group label="Al" name="to"><input id="field-to" type="date" name="to" class="form-in" value="{{ $to }}"></x-form-group>
        </div>
        <details class="finance-advanced"><summary>Filtri avanzati</summary><div class="finance-filter-grid">
            <x-form-group label="Importo minimo" name="min_amount"><input id="field-min-amount" type="number" min="0" step="0.01" name="min_amount" class="form-in" value="{{ $min_amount }}"></x-form-group>
            <x-form-group label="Importo massimo" name="max_amount"><input id="field-max-amount" type="number" min="0" step="0.01" name="max_amount" class="form-in" value="{{ $max_amount }}"></x-form-group>
            <x-finance-lookup type="clients" name="client_id" label="Cliente" :value="$client_id" :text="$clientLabel" />
            <x-finance-lookup type="projects" name="project_id" label="Progetto" :value="$project_id" :text="$projectLabel" :client="$client_id" />
            <x-form-group label="Collegamento" name="expenseable_type"><select id="field-expenseable-type" name="expenseable_type" class="form-sel">@foreach(['' => 'Tutti', 'client' => 'Cliente', 'project' => 'Progetto', 'task' => 'Task', 'ticket' => 'Ticket', 'hosting_service' => 'Dominio o hosting'] as $value => $label)<option value="{{ $value }}" @selected($expenseable_type === $value)>{{ $label }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Ordine nelle sottocategorie" name="sort"><select id="field-sort" name="sort" class="form-sel">@foreach(['due_asc' => 'Scadenza crescente', 'date_desc' => 'Data decrescente', 'date_asc' => 'Data crescente', 'amount_desc' => 'Importo decrescente', 'amount_asc' => 'Importo crescente'] as $value => $label)<option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>@endforeach</select></x-form-group>
        </div><a href="{{ route('expenses.categories') }}" wire:navigate>Gestisci categorie</a></details>
        <div class="u-flex u-gap-sm u-mt-md"><button type="submit" class="btn btn-p">Applica filtri</button><a href="{{ route('expenses.index') }}" class="btn btn-g">Azzera filtri</a></div>
    </form>
    @if($groups->isNotEmpty() || $category_id !== '')
        <div class="finance-category-filters u-mb-md">
            <div class="finance-category-badges" role="group" aria-label="Filtra spese per categoria">
                <button type="button" class="btn btn-g" wire:click="selectCategory('')" wire:loading.attr="disabled" wire:target="selectCategory,selectSubcategory" aria-pressed="{{ $category_id === '' ? 'true' : 'false' }}">Tutte le categorie</button>
                @foreach($groups as $category)
                    <button type="button" class="btn btn-g" wire:key="expense-category-{{ $category->category_id }}" wire:click="selectCategory('{{ $category->category_id }}')"
                        wire:loading.attr="disabled" wire:target="selectCategory,selectSubcategory" aria-pressed="{{ (string) $category_id === (string) $category->category_id ? 'true' : 'false' }}"
                        title="{{ $category->count }} spese · {{ number_format((float) $category->amount, 2, ',', '.') }} €">
                        {{ $category->name }} ({{ $category->count }})
                    </button>
                @endforeach
            </div>
            @if($groups->hasPages())<div class="u-mt-sm">{{ $groups->links() }}</div>@endif
            @if(is_numeric($category_id) && ($subcategories->isNotEmpty() || $subcategory_id !== ''))
                <div class="finance-subcategory-filters" wire:key="expense-subcategories-{{ $category_id }}">
                    <div class="finance-category-badges" role="group" aria-label="Filtra spese per sottocategoria">
                        <button type="button" class="btn btn-g" wire:click="selectSubcategory('')" wire:loading.attr="disabled" wire:target="selectCategory,selectSubcategory"
                            aria-pressed="{{ $subcategory_id === '' ? 'true' : 'false' }}">Tutte le sottocategorie</button>
                        @foreach($subcategories as $subcategory)
                            <button type="button" class="btn btn-g" wire:key="expense-subcategory-{{ $subcategory->id }}" wire:click="selectSubcategory('{{ $subcategory->id }}')"
                                wire:loading.attr="disabled" wire:target="selectCategory,selectSubcategory" aria-pressed="{{ (string) $subcategory_id === (string) $subcategory->id ? 'true' : 'false' }}">{{ $subcategory->name }}</button>
                        @endforeach
                        <button type="button" class="btn btn-g" wire:click="selectSubcategory('none')" wire:loading.attr="disabled" wire:target="selectCategory,selectSubcategory"
                            aria-pressed="{{ $subcategory_id === 'none' ? 'true' : 'false' }}">Senza sottocategoria</button>
                    </div>
                </div>
            @endif
            @error('subcategory_id')<p class="form-err" role="alert">{{ $message }}</p>@enderror
        </div>
    @endif
    <x-panel class="finance-expense-list">
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
                        <th class="t-actions">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $expense)
                    @if($loop->first || $expenses[$loop->index - 1]->category_id !== $expense->category_id || $expenses[$loop->index - 1]->subcategory_id !== $expense->subcategory_id)
                        <tr class="finance-category-heading"><th colspan="7" scope="rowgroup">{{ $expense->categoryGroup?->name ?? 'Senza categoria' }}{{ $expense->subcategory ? ' / '.$expense->subcategory->name : '' }}</th></tr>
                    @endif
                    <tr x-data @click="window.Livewire.navigate('{{ route('expenses.show', $expense) }}')" class="u-cursor-pointer hover-bg">
                        <td class="name-col">
                            <a href="{{ route('expenses.show', $expense) }}" wire:navigate @click.stop>{{ $expense->title }}</a>
                            @if($expense->recurrence)<div class="u-text-meta">Ricorrente</div>@endif
                            @if($expense->category)
                                <div class="u-text-meta u-text-muted u-mt-xs">{{ $expense->category }}</div>
                            @endif
                        </td>
                        <td class="mono-col">
                            &euro; {{ number_format($expense->amount, 2, ',', '.') }}
                        </td>
                        <td>
                            {{ $expense->supplier ?? '—' }}
                            <div class="u-text-meta">{{ $expense->document ? 'Documento collegato' : 'In attesa del documento' }}</div>
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
                        <td class="t-actions">
                            <a href="{{ route('expenses.edit', $expense) }}" wire:navigate class="btn btn-g btn-sm" @click.stop>Modifica</a>
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
