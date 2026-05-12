<x-app-layout title="Modifica Pagamento">
    <x-page-header
        eyebrow="Modulo · Amministrazione"
        
    >
    <x-slot:title><strong>Modifica</strong> pagamento</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('payments.show', $payment) }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('payments.update', $payment) }}" method="POST">
            @csrf
            @method('PATCH')
            
            <div class="form-row">
                <x-form-group label="Data Pagamento" name="payment_date" required>
                    <input type="date" name="payment_date" class="form-in @error('payment_date') is-invalid @enderror"
                           value="{{ old('payment_date', $payment->payment_date?->toDateString()) }}">
                </x-form-group>
                <x-form-group label="Importo" name="amount" required>
                    <input type="number" step="0.01" name="amount" class="form-in @error('amount') is-invalid @enderror"
                           value="{{ old('amount', $payment->amount) }}">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Metodo" name="method" required>
                    <select name="method" class="form-sel @error('method') is-invalid @enderror">
                        @foreach($methods as $m)
                            <option value="{{ $m }}" {{ old('method', $payment->method) == $m ? 'selected' : '' }}>{{ (new \App\Models\Payment(['method' => $m]))->method_label }}</option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Riferimento / CRO (opzionale)" name="reference">
                    <input name="reference" class="form-in @error('reference') is-invalid @enderror"
                           value="{{ old('reference', $payment->reference) }}" placeholder="Es. CRO-2026040201">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Fattura di Riferimento" name="invoice_id" required>
                    <div class="u-fake-input-disabled">
                        {{ $payment->invoice->number }} — {{ $payment->invoice->client?->name }}
                        <span class="u-ml-auto u-text-sm">(Fissa)</span>
                    </div>
                </x-form-group>
                <div class="u-pt-28">
                    <div class="u-info-box">
                        <span class="u-text-strong">Info:</span> Il Cliente e il Progetto sono già saldamente legati e la fattura non può essere alterata.
                    </div>
                </div>
            </div>

            <div class="form-row full">
                <x-form-group label="Note" name="notes">
                    <textarea name="notes" class="form-ta @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $payment->notes) }}</textarea>
                </x-form-group>
            </div>

            <div class="u-alert-box u-mb-md">
                <i data-lucide="info" class="u-icon-sm"></i>
                Questo pagamento aggiornerà in modo automatico il saldo della fattura associata.
            </div>

            <div class="modal-ft u-section-sep">
                <a href="{{ route('payments.show', $payment) }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Aggiorna Pagamento</button>
            </div>
        </form>
    </x-panel>


</x-app-layout>