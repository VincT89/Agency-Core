<x-app-layout title="Modifica fattura">
    <x-page-header eyebrow="Amministrazione">
        <x-slot:title><strong>Modifica</strong> fattura</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-g">Torna al dettaglio</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <div class="u-mb-lg inv-section-copy">
            <div class="u-text-strong">Dati modificabili</div>
            <div class="u-text-meta">
                Gli importi e l’IVA vengono ricalcolati dalle singole voci.
                Dopo la preparazione fiscale la fattura sarà bloccata.
            </div>
            @if($invoice->fiscal_number)
                <div class="u-text-meta u-mt-xs">
                    Numero fiscale già riservato: {{ $invoice->fiscal_number }}.
                    La data può cambiare soltanto all’interno dello stesso anno.
                </div>
            @endif
        </div>

        <form action="{{ route('invoices.update', $invoice) }}" method="POST">
            @csrf
            @method('PATCH')
            <input type="hidden" name="fiscal_document_type" value="TD01">
            @if($invoice->marketing_campaign_id)
                <input type="hidden" name="marketing_campaign_id" value="{{ $invoice->marketing_campaign_id }}">
            @endif

            <div class="form-row">
                <x-form-group label="Riferimento interno" name="number" required>
                    <input name="number" class="form-in @error('number') is-invalid @enderror"
                           value="{{ old('number', $invoice->number) }}">
                </x-form-group>
                <x-form-group label="Stato dell’incasso" name="status" required>
                    <select name="status" class="form-sel @error('status') is-invalid @enderror">
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(old('status', $invoice->status) === $status)>
                                {{ (new \App\Models\Invoice(['status' => $status]))->status_label }}
                            </option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Tipo documento" name="fiscal_document_type">
                    <input class="form-in" value="TD01 - Fattura ordinaria" readonly>
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Cliente" name="client_id" required>
                    <select name="client_id" id="client_sel"
                            class="form-sel @error('client_id') is-invalid @enderror">
                        <option value="">Seleziona cliente...</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}"
                                    @selected((int) old('client_id', $invoice->client_id) === $client->id)>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                </x-form-group>

                @if($invoice->marketing_campaign_id)
                    <div class="form-g">
                        <div class="form-lbl">Campagna collegata</div>
                        <input class="form-in"
                               value="{{ $invoice->marketingCampaign?->name ?? 'Campagna marketing' }}"
                               readonly>
                    </div>
                @else
                    <x-form-group label="Progetto" name="project_id" required>
                        <select name="project_id" id="project_sel"
                                class="form-sel @error('project_id') is-invalid @enderror" required>
                            <option value="">Seleziona progetto...</option>
                            @if($invoice->project)
                                <option value="{{ $invoice->project_id }}" selected>
                                    {{ $invoice->project->name }}
                                </option>
                            @endif
                        </select>
                    </x-form-group>
                @endif
            </div>

            <div class="form-row">
                <x-form-group label="Data fattura" name="issue_date" required>
                    <input type="date" name="issue_date"
                           class="form-in @error('issue_date') is-invalid @enderror"
                           value="{{ old('issue_date', $invoice->issue_date?->toDateString()) }}">
                </x-form-group>
                <x-form-group label="Data di scadenza" name="due_date">
                    <input type="date" name="due_date"
                           class="form-in @error('due_date') is-invalid @enderror"
                           value="{{ old('due_date', $invoice->due_date?->toDateString()) }}">
                </x-form-group>
                <x-form-group label="Valuta" name="currency" required>
                    <input name="currency" class="form-in @error('currency') is-invalid @enderror"
                           value="{{ old('currency', $invoice->currency) }}" maxlength="3">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Imponibile" name="subtotal" required>
                    <input type="number" step="0.01" name="subtotal" id="inv-subtotal"
                           class="form-in @error('subtotal') is-invalid @enderror"
                           value="{{ old('subtotal', $invoice->subtotal) }}" readonly>
                </x-form-group>
                <x-form-group label="IVA calcolata" name="tax_amount" required>
                    <input type="number" step="0.01" name="tax_amount" id="inv-tax"
                           class="form-in @error('tax_amount') is-invalid @enderror"
                           value="{{ old('tax_amount', $invoice->tax_amount) }}" readonly>
                </x-form-group>
                <div class="form-g">
                    <div class="form-lbl">Totale</div>
                    <input type="number" step="0.01" id="inv-total"
                           class="form-in" value="{{ $invoice->total }}" readonly>
                </div>
            </div>

            <div x-data="invoiceItemsHandler(@js(old('items', $existingItems->toArray())))"
                class="inv-items-builder">
                <div class="inv-items-builder-hd">
                    <div class="inv-section-copy">
                        <div class="form-lbl">Voci della fattura</div>
                        <div class="u-text-meta">
                            Per le voci collegate a contratti o extra puoi modificare soltanto i dati IVA.
                        </div>
                    </div>
                    <button type="button" @click="addLine()" class="btn btn-s btn-xs">
                        <i data-lucide="plus" class="u-icon-sm"></i>
                        Aggiungi voce manuale
                    </button>
                </div>

                <template x-for="(line, index) in lines" :key="line.key">
                    <div class="inv-fiscal-line">
                        <input type="hidden" :name="`items[${index}][id]`" :value="line.id ?? ''">
                        <input type="hidden" :name="`items[${index}][billable_type]`"
                               :value="line.billable_type ?? ''">

                        <div class="inv-line-field inv-line-desc">
                            <span class="form-lbl">Descrizione</span>
                            <input type="text" :name="`items[${index}][description]`"
                                   x-model="line.description" class="form-in"
                                   :readonly="line.linked" required>
                            <span x-show="line.linked" class="u-text-meta">Voce collegata</span>
                        </div>

                        <div class="inv-line-field inv-line-qty">
                            <span class="form-lbl">Quantità</span>
                            <input type="number" :name="`items[${index}][quantity]`"
                                   x-model.number="line.quantity" @input="calculate()"
                                   class="form-in" :readonly="line.linked"
                                   min="0.01" step="0.01" required>
                        </div>

                        <div class="inv-line-field inv-line-unit">
                            <span class="form-lbl">Unità</span>
                            <input type="text" :name="`items[${index}][unit_of_measure]`"
                                   x-model="line.unit_of_measure" class="form-in"
                                   maxlength="10">
                        </div>

                        <div class="inv-line-field inv-line-price">
                            <span class="form-lbl">Prezzo unitario</span>
                            <input type="number" :name="`items[${index}][unit_price]`"
                                   x-model.number="line.unit_price" @input="calculate()"
                                   class="form-in" :readonly="line.linked"
                                   min="0" step="0.01" required>
                        </div>

                        <div class="inv-line-field inv-line-vat">
                            <span class="form-lbl">IVA percentuale</span>
                            <input type="number" :name="`items[${index}][vat_rate]`"
                                   x-model.number="line.vat_rate" @input="calculate()"
                                   class="form-in" min="0" max="100" step="0.01" required>
                        </div>

                        <div class="inv-line-field inv-line-nature" x-show="isZeroVat(line)">
                            <span class="form-lbl">Natura IVA</span>
                            <select :name="`items[${index}][vat_nature]`"
                                    x-model="line.vat_nature" class="form-sel"
                                    :required="isZeroVat(line)">
                                <option value="">Seleziona...</option>
                                @foreach($vatNatures as $nature)
                                    <option value="{{ $nature->value }}">{{ $nature->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="inv-line-field inv-line-reference" x-show="isZeroVat(line)">
                            <span class="form-lbl">Riferimento normativo</span>
                            <input type="text" :name="`items[${index}][vat_reference]`"
                                   x-model="line.vat_reference" class="form-in"
                                   :required="isZeroVat(line)" placeholder="Norma applicata">
                        </div>

                        <button x-show="! line.linked" type="button" @click="removeLine(index)"
                                class="btn-ghost-danger inv-line-remove"
                                aria-label="Rimuovi voce" title="Rimuovi voce">
                            <i data-lucide="trash-2" class="u-icon-sm"></i>
                            <span>Rimuovi</span>
                        </button>
                    </div>
                </template>

                <div x-show="lines.length === 0" class="inv-items-empty">
                    Aggiungi almeno una voce per poter preparare la fattura elettronica.
                </div>
            </div>

            @if($errors->hasAny(['items', 'items.*']))
                <div class="form-err u-mt-sm">
                    Controlla i dati IVA e gli importi delle voci.
                </div>
            @endif

            <div class="form-row full u-mt-md">
                <x-form-group label="Note interne" name="notes">
                    <textarea name="notes" class="form-ta @error('notes') is-invalid @enderror"
                              rows="3">{{ old('notes', $invoice->notes) }}</textarea>
                </x-form-group>
            </div>

            <div class="modal-ft form-footer-sep">
                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva modifiche</button>
            </div>
        </form>
    </x-panel>

    @push('scripts')
        <script>
            function invoiceItemsHandler(existingLines = []) {
                return {
                    lines: existingLines.map((line, index) => ({
                        key: line.id ? `stored-${line.id}` : `old-${index}`,
                        id: line.id ?? null,
                        billable_type: line.billable_type ?? '',
                        linked: Boolean(line.billable_type),
                        description: line.description ?? '',
                        quantity: parseFloat(line.quantity ?? 1),
                        unit_price: line.unit_price ?? '',
                        unit_of_measure: line.unit_of_measure ?? 'NR',
                        vat_rate: line.vat_rate === null || line.vat_rate === '' || typeof line.vat_rate === 'undefined'
                            ? 22
                            : parseFloat(line.vat_rate),
                        vat_nature: line.vat_nature ?? '',
                        vat_reference: line.vat_reference ?? '',
                    })),
                    nextKey: existingLines.length,
                    init() {
                        this.calculate();
                    },
                    addLine() {
                        this.lines.push({
                            key: `new-${this.nextKey++}`,
                            id: null,
                            billable_type: '',
                            linked: false,
                            description: '',
                            quantity: 1,
                            unit_price: '',
                            unit_of_measure: 'NR',
                            vat_rate: 22,
                            vat_nature: '',
                            vat_reference: '',
                        });
                    },
                    removeLine(index) {
                        if (this.lines[index]?.linked) {
                            return;
                        }

                        this.lines.splice(index, 1);
                        this.calculate();
                    },
                    calculate() {
                        const totals = this.lines.reduce((result, line) => {
                            const quantity = parseFloat(line.quantity) || 0;
                            const price = parseFloat(line.unit_price) || 0;
                            const vatRate = parseFloat(line.vat_rate);
                            const taxable = Math.round((quantity * price + Number.EPSILON) * 100) / 100;
                            const tax = Number.isFinite(vatRate)
                                ? Math.round((taxable * vatRate / 100 + Number.EPSILON) * 100) / 100
                                : 0;

                            result.subtotal += taxable;
                            result.tax += tax;

                            return result;
                        }, { subtotal: 0, tax: 0 });

                        const subtotalInput = document.getElementById('inv-subtotal');
                        const taxInput = document.getElementById('inv-tax');
                        const totalInput = document.getElementById('inv-total');

                        if (subtotalInput) subtotalInput.value = totals.subtotal.toFixed(2);
                        if (taxInput) taxInput.value = totals.tax.toFixed(2);
                        if (totalInput) totalInput.value = (totals.subtotal + totals.tax).toFixed(2);
                    },
                    isZeroVat(line) {
                        return line.vat_rate !== ''
                            && line.vat_rate !== null
                            && parseFloat(line.vat_rate) === 0;
                    },
                };
            }

            document.addEventListener('DOMContentLoaded', () => {
                if (
                    typeof initProjectSelect !== 'undefined'
                    && document.getElementById('project_sel')
                ) {
                    initProjectSelect(
                        'client_sel',
                        'project_sel',
                        @js(old('project_id', $invoice->project_id))
                    );
                }
            });
        </script>
    @endpush
</x-app-layout>
