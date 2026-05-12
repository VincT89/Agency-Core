<x-app-layout title="Nuovo Pagamento">
    <x-page-header
        eyebrow="Modulo · Amministrazione"
        
    >
    <x-slot:title><strong>Registra</strong> pagamento</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('payments.index') }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('payments.store') }}" method="POST">
            @csrf
            
            <div class="form-row">
                <x-form-group label="Data Pagamento" name="payment_date" required>
                    <input type="date" name="payment_date" class="form-in @error('payment_date') is-invalid @enderror"
                           value="{{ old('payment_date', now()->toDateString()) }}">
                </x-form-group>
                <x-form-group label="Importo" name="amount" required>
                    <input type="number" step="0.01" name="amount" class="form-in @error('amount') is-invalid @enderror"
                           value="{{ old('amount') }}" placeholder="Es. 1500.00">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Metodo" name="method" required>
                    <select name="method" class="form-sel @error('method') is-invalid @enderror">
                        @foreach($methods as $m)
                            <option value="{{ $m }}" {{ old('method', 'bank_transfer') == $m ? 'selected' : '' }}>{{ (new \App\Models\Payment(['method' => $m]))->method_label }}</option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Riferimento / CRO (opzionale)" name="reference">
                    <input name="reference" class="form-in @error('reference') is-invalid @enderror"
                           value="{{ old('reference') }}" placeholder="Es. CRO-2026040201">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Fattura di Riferimento" name="invoice_id" required>
                    <select name="invoice_id" id="invoice_select"
                            class="form-sel @error('invoice_id') is-invalid @enderror">
                        <option value="">Seleziona fattura...</option>
                        @foreach($invoices as $invoice)
                            <option value="{{ $invoice->id }}"
                                    {{ (old('invoice_id') ?? $preselectedInvoice?->id) == $invoice->id ? 'selected' : '' }}>
                                {{ $invoice->number }} — {{ $invoice->client?->name }}
                                (Residuo: € {{ number_format($invoice->residual, 2, ',', '.') }})
                            </option>
                        @endforeach
                    </select>
                </x-form-group>
                <div class="u-pt-28">
                    <div class="u-info-box">
                        <span class="u-text-strong">Info:</span> Il Cliente e il Progetto verranno automaticamente derivati dalla fattura selezionata.
                    </div>
                </div>
            </div>

            <div class="form-row full">
                <x-form-group label="Note" name="notes">
                    <textarea name="notes" class="form-ta @error('notes') is-invalid @enderror" rows="3">{{ old('notes') }}</textarea>
                </x-form-group>
            </div>

            <div class="u-alert-box u-mb-md">
                <i data-lucide="info" class="u-icon-sm"></i>
                Questo pagamento aggiornerà in modo automatico il saldo ("Da Incassare" e "Incassato") della fattura selezionata.
            </div>

            <div class="modal-ft u-section-sep">
                <a href="{{ route('payments.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva Pagamento</button>
            </div>
        </form>
    </x-panel>


</x-app-layout>