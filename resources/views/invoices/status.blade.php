<x-app-layout title="Modifica stato fattura {{ $invoice->display_number }}">
    @php
        $money = fn ($amount) => number_format((float) $amount, 2, ',', '.').' '.($invoice->currency === 'EUR' ? '€' : $invoice->currency);
        $statuses = ['issued' => 'Da incassare', 'partially_paid' => 'Parzialmente incassata', 'paid' => 'Incassata', 'overdue' => 'Scaduta', 'cancelled' => 'Annullata nel gestionale'];
    @endphp
    <div class="invoice-detail invoice-status-page">
        <div class="page-back-row"><a href="{{ route('invoices.show', $invoice) }}" class="btn btn-g">Torna alla fattura</a></div>
        <x-page-header>
            <x-slot:title>Modifica stato</x-slot:title>
            <x-slot:meta>Fattura {{ $invoice->display_number }} · {{ $invoice->client?->company_name ?: $invoice->client?->name }}</x-slot:meta>
        </x-page-header>
        <x-panel padded>
            <dl class="invoice-status-summary">
                <div><dt>Stato attuale</dt><dd>{{ $invoice->status_label }}</dd></div>
                <div><dt>Incassato</dt><dd>{{ $money($invoice->paid_total) }}</dd></div>
                <div><dt>Da incassare</dt><dd>{{ $money($invoice->residual) }}</dd></div>
            </dl>
            @if($errors->any())
                <div class="invoice-status-errors" role="alert">
                    @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
                </div>
            @endif
            <form id="invoice-status-form" method="POST" action="{{ route('invoices.status.update', $invoice) }}"
                x-data="{ status: @js(old('status', array_key_exists($invoice->status, $statuses) ? $invoice->status : 'issued')), submitting: false, hasResidual: @js($invoice->residual > 0), get receipt() { return this.hasResidual && ['paid', 'partially_paid'].includes(this.status); } }"
                @submit="if (submitting) { $event.preventDefault(); return; } submitting = true">
                @csrf
                @method('PATCH')
                <input type="hidden" name="version" value="{{ $version }}">
                <x-form-group label="Nuovo stato" name="status" for="invoice-status" required>
                    {{-- Attende l’inizializzazione del modulo per conservare anche una selezione molto rapida. --}}
                    <select id="invoice-status" name="status" class="form-sel" x-model="status" disabled :disabled="false" required>
                        @foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', $invoice->status) === $value)>{{ $label }}</option>@endforeach
                    </select>
                </x-form-group>

                <div x-show="receipt" x-cloak class="invoice-status-receipt">
                    <p x-show="status === 'paid'">Verrà registrato un incasso a saldo di <strong>{{ $money($invoice->residual) }}</strong>.</p>
                    <p x-show="status === 'partially_paid'">Indica il nuovo incasso da aggiungere ai pagamenti già registrati.</p>
                    <div class="form-row" x-show="status === 'partially_paid'">
                        <x-form-group label="Nuovo incasso (€)" name="amount" for="invoice-status-amount">
                            <input id="invoice-status-amount" name="amount" type="number" class="form-in" min="0.01" step="0.01" max="{{ number_format($invoice->residual, 2, '.', '') }}" value="{{ old('amount') }}" :required="receipt && status === 'partially_paid'" :disabled="!receipt || status !== 'partially_paid'">
                        </x-form-group>
                    </div>
                    <div class="form-row">
                        <x-form-group label="Data dell’incasso" name="payment_date" for="invoice-status-date">
                            <input id="invoice-status-date" name="payment_date" type="date" class="form-in" value="{{ old('payment_date') }}" :required="receipt" :disabled="!receipt">
                        </x-form-group>
                        <x-form-group label="Metodo di pagamento" name="method" for="invoice-status-method">
                            <select id="invoice-status-method" name="method" class="form-sel" :required="receipt" :disabled="!receipt">
                                <option value="">Seleziona metodo</option>
                                @foreach(\App\Models\Payment::METHODS as $method)<option value="{{ $method }}" @selected(old('method') === $method)>{{ (new \App\Models\Payment(['method' => $method]))->method_label }}</option>@endforeach
                            </select>
                        </x-form-group>
                    </div>
                    <x-form-group label="Riferimento del pagamento (facoltativo)" name="reference" for="invoice-status-reference">
                        <input id="invoice-status-reference" name="reference" class="form-in" maxlength="255" value="{{ old('reference') }}" :disabled="!receipt">
                    </x-form-group>
                </div>

                <p class="invoice-status-help" x-show="status === 'cancelled'" x-cloak>L’annullamento vale solo nel gestionale: non invia richieste ad Aruba o allo SdI e conserva il documento originale. Se sono presenti incassi o note di credito collegate, occorre prima verificarli.</p>
                <p class="invoice-status-help" x-show="['issued', 'overdue'].includes(status)" x-cloak>Questo stato richiede che non ci siano incassi registrati. Se hai inserito un pagamento per errore, correggilo o rimuovilo dalla sua scheda.</p>
                <div class="invoice-status-actions">
                    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-g">Indietro</a>
                    <button class="btn btn-p" type="submit" x-show="status !== 'cancelled'" :disabled="submitting">Salva stato</button>
                    <div x-show="status === 'cancelled'" x-cloak>
                        <x-confirm-modal title="Annulla fattura nel gestionale" message="La fattura verrà esclusa dagli importi da incassare. Il documento originale sarà conservato e non verrà inviata alcuna richiesta ad Aruba o allo SdI." confirmText="Conferma annullamento" form-id="invoice-status-form" confirmClass="btn btn-p btn-danger" variant="danger">
                            <button class="btn btn-p btn-danger" type="button" :disabled="submitting">Annulla fattura</button>
                        </x-confirm-modal>
                    </div>
                </div>
            </form>
        </x-panel>
        @if($invoice->payments->isNotEmpty())
            <x-panel padded>
                <x-slot:title><h2>Pagamenti registrati</h2></x-slot:title>
                @foreach($invoice->payments as $payment)
                    <a class="invoice-payment" href="{{ route('payments.show', $payment) }}"><span>{{ $payment->payment_date?->format('d/m/Y') }}<small>{{ $payment->method_label }}</small></span><strong>{{ $money($payment->amount) }}</strong></a>
                @endforeach
            </x-panel>
        @endif
    </div>
</x-app-layout>
