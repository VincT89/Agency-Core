<x-app-layout title="Modifica Fattura">
    <x-page-header
        eyebrow="Modulo · Amministrazione"
        
    >
    <x-slot:title><strong>Modifica</strong> fattura</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('invoices.update', $invoice) }}" method="POST">
            @csrf
            @method('PATCH')
            
            <div class="form-row">
                <x-form-group label="Numero Fattura" name="number" required>
                    <input name="number" class="form-in @error('number') is-invalid @enderror"
                           value="{{ old('number', $invoice->number) }}">
                </x-form-group>
                <x-form-group label="Stato" name="status" required>
                    <select name="status" class="form-sel @error('status') is-invalid @enderror">
                        @foreach($statuses as $s)
                            <option value="{{ $s }}" {{ old('status', $invoice->status) == $s ? 'selected' : '' }}>{{ (new \App\Models\Invoice(['status' => $s]))->status_label }}</option>
                        @endforeach
                    </select>
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Cliente" name="client_id" required>
                    <select name="client_id" id="client_sel" class="form-sel @error('client_id') is-invalid @enderror">
                        <option value="">Seleziona cliente...</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id', $invoice->client_id) == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Progetto" name="project_id" required>
                    <select name="project_id" id="project_sel" class="form-sel @error('project_id') is-invalid @enderror" required>
                        <option value="">Nessun progetto...</option>
                        @if($invoice->project)
                            <option value="{{ $invoice->project_id }}" selected>{{ $invoice->project->name }}</option>
                        @endif
                    </select>
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Data di emissione" name="issue_date" required>
                    <input type="date" name="issue_date" class="form-in @error('issue_date') is-invalid @enderror"
                           value="{{ old('issue_date', $invoice->issue_date?->toDateString()) }}">
                </x-form-group>
                <x-form-group label="Data di scadenza" name="due_date">
                    <input type="date" name="due_date" class="form-in @error('due_date') is-invalid @enderror"
                           value="{{ old('due_date', $invoice->due_date?->toDateString()) }}">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Imponibile (Subtotale)" name="subtotal" required>
                    <input type="number" step="0.01" name="subtotal" class="form-in @error('subtotal') is-invalid @enderror"
                           value="{{ old('subtotal', $invoice->subtotal) }}">
                </x-form-group>
                <x-form-group label="Tasse/IVA" name="tax_amount" required>
                    <input type="number" step="0.01" name="tax_amount" class="form-in @error('tax_amount') is-invalid @enderror"
                           value="{{ old('tax_amount', $invoice->tax_amount) }}">
                </x-form-group>
                <x-form-group label="Valuta" name="currency">
                    <input name="currency" class="form-in @error('currency') is-invalid @enderror"
                           value="{{ old('currency', $invoice->currency) }}">
                </x-form-group>
            </div>

            <div class="modal-ft" style="border-top:1px solid var(--line);padding-top:16px;margin-top:16px">
                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Aggiorna Fattura</button>
            </div>
        </form>
    </x-panel>


    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        if(typeof initProjectSelect !== 'undefined') {
            initProjectSelect('client_sel', 'project_sel', {{ $invoice->project_id ?? "null" }});
        }
    });
    </script>
    @endpush
</x-app-layout>