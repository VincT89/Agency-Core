<div>
    <x-page-header>
        <x-slot:title>Categorie delle spese</x-slot:title>
        <x-slot:actions><a href="{{ route('expenses.index') }}" class="btn btn-g" wire:navigate>Torna alle spese</a></x-slot:actions>
    </x-page-header>
    @error('delete')<p class="u-alert-error" role="alert">{{ $message }}</p>@enderror
    <x-panel padded class="u-mb-lg">
        <h2 class="u-mb-md">{{ $editingId ? 'Modifica categoria' : 'Nuova categoria o sottocategoria' }}</h2>
        <form wire:submit="save" class="finance-form">
            <div class="form-row">
                <x-form-group name="name" label="Nome" required><input id="field-name" class="form-in" wire:model="name" maxlength="255" required></x-form-group>
                <x-form-group name="parent_id" label="Categoria principale">
                    <select id="field-parent-id" class="form-sel" wire:model="parent_id" @disabled($editingId)>
                        <option value="">Nessuna: crea una categoria principale</option>
                        @foreach($parents as $parent)<option value="{{ $parent->id }}">{{ $parent->name }}{{ $parent->active ? '' : ' (archiviata)' }}</option>@endforeach
                    </select>
                </x-form-group>
            </div>
            <label class="u-flex u-gap-sm u-mb-md"><input type="checkbox" wire:model="active">Disponibile per nuove spese</label>
            <p class="u-text-meta u-mb-md">Archiviare una categoria la nasconde dalle nuove selezioni. Lo storico resta consultabile; le sottocategorie sono selezionabili solo se la categoria principale è attiva.</p>
            <div class="u-flex u-gap-sm"><button type="submit" class="btn btn-p">Salva categoria</button>@if($editingId)<button type="button" class="btn btn-g" wire:click="cancel">Annulla modifica</button>@endif</div>
        </form>
    </x-panel>
    <x-panel padded>
        <label for="category-search" class="form-lbl">Cerca categoria</label>
        <input id="category-search" type="search" class="form-in u-mb-md" wire:model.live.debounce.300ms="search">
        <div class="finance-directory">
            @forelse($categories as $category)
                <div class="finance-directory-entry" wire:key="category-{{ $category->id }}-{{ md5($category->name.'|'.$category->children_count) }}">
                    <div><strong>{{ $category->name }}</strong><p class="u-text-meta">{{ $category->parent?->name ?? 'Categoria principale' }}{{ $category->active ? '' : ' · Archiviata' }}</p></div>
                    <div class="u-flex u-gap-sm u-flex-wrap">
                        <button type="button" class="btn btn-g btn-sm" wire:click="edit({{ $category->id }})" aria-label="Modifica {{ $category->name }}">Modifica</button>
                        <x-confirm-modal :title="$category->parent_id ? 'Elimina sottocategoria' : 'Elimina categoria'"
                            :message="'Eliminare '.$category->name.($category->children_count ? ' e le sue '.$category->children_count.' sottocategorie' : '').'? Le voci già usate da spese o ricorrenze devono essere archiviate.'"
                            confirmText="Conferma eliminazione" :confirm-method="'delete('.$category->id.')'" confirmClass="btn btn-p btn-danger" variant="danger">
                            <button type="button" class="btn btn-g btn-sm u-text-red" wire:loading.attr="disabled" wire:target="delete" aria-label="Elimina {{ $category->name }}">Elimina</button>
                        </x-confirm-modal>
                    </div>
                </div>
            @empty<p>Nessuna categoria trovata.</p>@endforelse
        </div>
        {{ $categories->links() }}
    </x-panel>
</div>
