<x-app-layout :title="$income->exists ? 'Modifica entrata' : 'Nuova entrata'">
    <x-page-header><x-slot:title>{{ $income->exists ? 'Modifica entrata' : 'Nuova entrata' }}</x-slot:title></x-page-header>
    @include('expenses.partials.navigation')
    <x-panel padded>
        <form class="finance-form" method="POST" action="{{ $income->exists ? route('expenses.incomes.update', $income) : route('expenses.incomes.store') }}">
            @csrf
            @if($income->exists) @method('PUT') @endif
            <p class="u-mb-md">Usa questa voce per entrate fuori fattura. I pagamenti delle fatture clienti sono già conteggiati dal gestionale.</p>
            <div class="form-row">
                <x-form-group label="Descrizione" name="title" required><input id="field-title" name="title" class="form-in" maxlength="255" value="{{ old('title', $income->title) }}" required></x-form-group>
                <x-form-group label="Importo (EUR)" name="amount" required><input id="field-amount" name="amount" type="number" min="0.01" max="99999999.99" step="0.01" class="form-in" value="{{ old('amount', $income->amount) }}" required></x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Pagante" name="payer"><input id="field-payer" name="payer" class="form-in" maxlength="255" value="{{ old('payer', $income->payer) }}"></x-form-group>
                <x-form-group label="Cliente collegato (facoltativo)" name="client_id"><select id="field-client-id" name="client_id" class="form-sel"><option value="">Nessun cliente</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected((string)old('client_id', $income->client_id) === (string)$client->id)>{{ $client->name }}</option>@endforeach</select></x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Data prevista" name="expected_on" required><input id="field-expected-on" type="date" name="expected_on" class="form-in" value="{{ old('expected_on', $income->expected_on?->toDateString()) }}" required></x-form-group>
                <x-form-group label="Stato" name="status" required><select id="field-status" name="status" class="form-sel">@foreach(\App\Models\ManualIncome::STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('status', $income->status) === $value)>{{ $label }}</option>@endforeach</select></x-form-group>
            </div>
            <div class="form-row">
                <x-form-group label="Data incasso effettivo" name="received_on"><input id="field-received-on" type="date" name="received_on" class="form-in" max="{{ today()->toDateString() }}" value="{{ old('received_on', $income->received_on?->toDateString()) }}"><p class="u-text-meta">Obbligatoria quando selezioni “Incassata”.</p></x-form-group>
            </div>
            <x-form-group label="Note" name="notes"><textarea id="field-notes" name="notes" class="form-ta" rows="3" maxlength="10000">{{ old('notes', $income->notes) }}</textarea></x-form-group>
            <div class="modal-ft u-section-sep"><a href="{{ route('expenses.incomes.index') }}" class="btn btn-g">Annulla</a><button class="btn btn-p" type="submit">Salva entrata</button></div>
        </form>
    </x-panel>
</x-app-layout>
