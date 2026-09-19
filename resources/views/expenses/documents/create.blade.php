<x-app-layout title="Carica documento di spesa">
    <x-page-header><x-slot:title>Carica documento di spesa</x-slot:title></x-page-header>
    @include('expenses.partials.navigation')
    <x-panel padded>
        <form class="finance-form" method="POST" enctype="multipart/form-data" action="{{ route('expenses.documents.store') }}">
            @csrf
            @error('document')<p class="invalid-feedback" role="alert">{{ $message }}</p>@enderror
            <p class="u-mb-md">Inserisci i riferimenti del documento e allega il file. Per gli stipendi seleziona Cedolino e indica la persona a cui si riferisce nel campo beneficiario.</p>
            <div class="form-row">
                <x-form-group label="Tipo di documento" name="kind" required><select id="field-kind" name="kind" class="form-sel">@foreach(\App\Models\ExpenseDocument::KINDS as $value => $label)<option value="{{ $value }}" @selected(old('kind', 'invoice') === $value)>{{ $label }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Numero / riferimento" name="number" required><input id="field-number" name="number" class="form-in" value="{{ old('number') }}" maxlength="100" required></x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Emittente / beneficiario" name="issuer" required><input id="field-issuer" name="issuer" class="form-in" value="{{ old('issuer') }}" maxlength="255" required></x-form-group>
                <x-form-group label="Partita IVA / codice fiscale (obbligatorio per fatture)" name="issuer_identifier"><input id="field-issuer-identifier" name="issuer_identifier" class="form-in" value="{{ old('issuer_identifier') }}" maxlength="50"></x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Data documento" name="document_date" required><input id="field-document-date" name="document_date" type="date" class="form-in" value="{{ old('document_date', today()->toDateString()) }}" required></x-form-group>
                <x-form-group label="Scadenza (facoltativa)" name="due_date"><input id="field-due-date" name="due_date" type="date" class="form-in" value="{{ old('due_date') }}"></x-form-group>
            </div>
            <x-form-group label="Paese dell’emittente (codice di due lettere, obbligatorio per fatture)" name="issuer_country"><input id="field-issuer-country" name="issuer_country" class="form-in" value="{{ old('issuer_country', 'IT') }}" maxlength="2" placeholder="IT"></x-form-group>
            <p class="u-text-meta u-mb-md">Inserisci la partita IVA senza il prefisso del paese, indicato nel campo separato. Questo permette di riconoscere la stessa fattura anche quando proviene da Aruba.</p>
            <x-form-group label="Importo da pagare documentato (EUR)" name="amount" required><input id="field-amount" name="amount" type="number" min="0.01" max="99999999.99" step="0.01" class="form-in" value="{{ old('amount') }}" required></x-form-group>
            <p class="u-text-meta u-mb-md">Per un cedolino indica il netto da corrispondere. Eventuali contributi vanno registrati come uscite distinte con il loro giustificativo.</p>
            <x-form-group label="File (PDF, XML, JPG o PNG, massimo 10 MB)" name="file" required><input id="field-file" name="file" type="file" class="form-in" accept=".pdf,.xml,.jpg,.jpeg,.png" required></x-form-group>
            <p class="u-text-meta u-mb-md">I documenti sono accessibili solo ad Admin e Amministrazione. Se gli stessi riferimenti sono già presenti, viene riutilizzato il documento esistente.</p>
            <div class="modal-ft u-section-sep"><a href="{{ route('expenses.documents.index') }}" class="btn btn-g">Annulla</a><button class="btn btn-p" type="submit">Salva documento</button></div>
        </form>
    </x-panel>
</x-app-layout>
