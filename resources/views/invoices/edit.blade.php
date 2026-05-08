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
                           id="inv-subtotal" readonly
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

            {{-- Voci Fattura --}}
            <div x-data="invoiceItemsHandler({{ $linkedTotal }}, {{ $existingItems->toJson() }})" class="inv-items-builder">
                <div class="inv-items-builder-hd">
                    <div class="form-lbl">Voci Fattura</div>
                    <button type="button" @click="addLine()" class="btn btn-s btn-xs">
                        <i data-lucide="plus" class="u-icon-sm"></i> Aggiungi voce
                    </button>
                </div>

                <template x-for="(line, i) in lines" :key="i">
                    <div class="inv-custom-line-row">
                        <input type="hidden"   :name="`items[${i}][id]`" :value="line.id ?? ''">
                        <input type="text"     :name="`items[${i}][description]`"
                               x-model="line.description" @input="calculate()"
                               class="form-in inv-line-desc" placeholder="Descrizione voce" required>
                        <input type="number"   :name="`items[${i}][quantity]`"
                               x-model.number="line.quantity" @input="calculate()"
                               class="form-in inv-line-qty" placeholder="Qtà" min="0.01" step="0.01" required>
                        <input type="number"   :name="`items[${i}][unit_price]`"
                               x-model.number="line.unit_price" @input="calculate()"
                               class="form-in inv-line-price" placeholder="€ Prezzo" min="0" step="0.01" required>
                        <button type="button" @click="removeLine(i)" class="btn-ghost-danger">
                            <i data-lucide="trash-2" class="u-icon-sm"></i>
                        </button>
                    </div>
                </template>

                <div x-show="lines.length === 0" class="inv-items-empty">
                    Nessuna voce — clicca "Aggiungi voce" per iniziare.
                </div>
            </div>

            <div class="modal-ft form-footer-sep">
                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Aggiorna Fattura</button>
            </div>
        </form>
    </x-panel>


    @push('scripts')
    <script>
    function invoiceItemsHandler(linkedTotal, existingLines = []) {
        return {
            lines: existingLines.map(l => ({
                id: l.id,
                description: l.description,
                quantity: parseFloat(l.quantity),
                unit_price: parseFloat(l.unit_price),
            })),
            linkedTotal: linkedTotal,
            addLine() {
                this.lines.push({ id: null, description: '', quantity: 1, unit_price: '' });
            },
            removeLine(i) {
                this.lines.splice(i, 1);
                this.calculate();
            },
            calculate() {
                const manualTotal = this.lines.reduce((sum, l) => {
                    const qty   = parseFloat(l.quantity)   || 0;
                    const price = parseFloat(l.unit_price) || 0;
                    return sum + (qty * price);
                }, 0);
                const subtotal = this.linkedTotal + manualTotal;
                const el = document.getElementById('inv-subtotal');
                if (el) el.value = subtotal.toFixed(2);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if(typeof initProjectSelect !== 'undefined') {
            initProjectSelect('client_sel', 'project_sel', {{ $invoice->project_id ?? "null" }});
        }
    });
    </script>
    @endpush
</x-app-layout>