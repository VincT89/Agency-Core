<x-app-layout :title="$recurrence->exists ? 'Modifica ricorrenza' : 'Nuova ricorrenza'">
    <x-page-header><x-slot:title>{{ $recurrence->exists ? 'Modifica ricorrenza' : 'Nuova ricorrenza' }}</x-slot:title></x-page-header>
    @include('expenses.partials.navigation')
    <x-panel padded>
        <form class="finance-form" method="POST" action="{{ $recurrence->exists ? route('expenses.recurrences.update', $recurrence) : route('expenses.recurrences.store') }}">
            @csrf
            @if($recurrence->exists) @method('PUT') @endif
            <p class="u-mb-md">La ricorrenza genera previsioni. Ogni pagamento richiede una conferma e il documento appropriato per quella scadenza.</p>
            <div class="form-row">
                <x-form-group label="Descrizione" name="title" required><input id="field-title" name="title" class="form-in" value="{{ old('title', $recurrence->title) }}" maxlength="255" required></x-form-group>
                <x-form-group label="Importo previsto per scadenza (EUR)" name="amount" required><input id="field-amount" name="amount" type="number" min="0.01" max="99999999.99" step="0.01" class="form-in" value="{{ old('amount', $recurrence->amount) }}" required></x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Categoria" name="category"><input id="field-category" name="category" class="form-in" value="{{ old('category', $recurrence->category) }}" maxlength="255"></x-form-group>
                <x-form-group label="Fornitore / beneficiario" name="supplier"><input id="field-supplier" name="supplier" class="form-in" value="{{ old('supplier', $recurrence->supplier) }}" maxlength="255"></x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Documento richiesto al pagamento" name="document_kind" required>
                    <select id="field-document-kind" name="document_kind" class="form-sel">
                        @foreach(\App\Models\ExpenseDocument::KINDS as $value => $label)<option value="{{ $value }}" @selected(old('document_kind', $recurrence->document_kind) === $value)>{{ $label }}</option>@endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Frequenza" name="frequency" required>
                    <select id="field-frequency" name="frequency" class="form-sel">
                        @foreach(\App\Models\ExpenseRecurrence::FREQUENCIES as $value => $label)
                            @if(!$recurrence->exists || $recurrence->frequency === $value)<option value="{{ $value }}" @selected(old('frequency', $recurrence->frequency) === $value)>{{ $label }}</option>@endif
                        @endforeach
                    </select>
                </x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Prima scadenza" name="starts_on" required><input id="field-starts-on" type="date" name="starts_on" class="form-in" value="{{ old('starts_on', $recurrence->starts_on?->toDateString()) }}" @readonly($recurrence->exists) required></x-form-group>
                <x-form-group label="Ultima scadenza (facoltativa)" name="ends_on"><input id="field-ends-on" type="date" name="ends_on" class="form-in" value="{{ old('ends_on', $recurrence->ends_on?->toDateString()) }}"></x-form-group>
            </div>
            @if($recurrence->exists)
                <p class="u-text-meta u-mb-md">Le modifiche si applicano alle scadenze future non pagate, prive di documento e non modificate singolarmente. Per cambiare frequenza o prima scadenza, termina questa ricorrenza e creane una nuova.</p>
            @endif
            <div class="form-row">
                <x-form-group label="Stato ricorrenza" name="active" required><select id="field-active" name="active" class="form-sel"><option value="1" @selected(old('active', $recurrence->active))>Attiva</option><option value="0" @selected(!old('active', $recurrence->active))>Sospesa</option></select></x-form-group>
            </div>
            <p class="u-text-meta u-mb-md">La sospensione annulla le previsioni future ancora da confermare. I pagamenti, i documenti collegati e le scadenze modificate singolarmente restano disponibili.</p>
            <x-form-group label="Note" name="notes"><textarea id="field-notes" name="notes" class="form-ta" rows="3" maxlength="10000">{{ old('notes', $recurrence->notes) }}</textarea></x-form-group>
            <div class="modal-ft u-section-sep"><a href="{{ route('expenses.recurrences.index') }}" class="btn btn-g">Annulla</a><button class="btn btn-p" type="submit">Salva ricorrenza</button></div>
        </form>
    </x-panel>
</x-app-layout>
