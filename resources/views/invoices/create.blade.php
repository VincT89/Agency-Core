<x-app-layout title="Nuova Fattura">
    <x-page-header
        eyebrow="Modulo · Amministrazione"
        
    >
    <x-slot:title><strong>Nuova</strong> fattura</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('invoices.index') }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('invoices.store') }}" method="POST">
            @csrf
            
            <div class="form-row">
                <x-form-group label="Numero Fattura" name="number" required>
                    <input name="number" class="form-in @error('number') is-invalid @enderror"
                           value="{{ old('number') }}" placeholder="Es. FAT-020-24">
                </x-form-group>
                <x-form-group label="Stato" name="status" required>
                    <select name="status" class="form-sel @error('status') is-invalid @enderror">
                        @foreach($statuses as $s)
                            <option value="{{ $s }}" {{ old('status', 'draft') == $s ? 'selected' : '' }}>{{ (new \App\Models\Invoice(['status' => $s]))->status_label }}</option>
                        @endforeach
                    </select>
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Cliente" name="client_id" required>
                    <select name="client_id" id="client_sel" class="form-sel @error('client_id') is-invalid @enderror">
                        <option value="">Seleziona cliente...</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Progetto" name="project_id" required>
                    <select name="project_id" id="project_sel" class="form-sel @error('project_id') is-invalid @enderror" required>
                        <option value="">Nessun progetto...</option>
                    </select>
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Data di emissione" name="issue_date" required>
                    <input type="date" id="issue_date" name="issue_date" class="form-in @error('issue_date') is-invalid @enderror"
                           value="{{ old('issue_date', now()->toDateString()) }}">
                </x-form-group>
                <x-form-group label="Data di scadenza" name="due_date">
                    <input type="date" id="due_date" name="due_date" class="form-in @error('due_date') is-invalid @enderror"
                           value="{{ old('due_date', now()->addDays(30)->toDateString()) }}">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Imponibile (Subtotale)" name="subtotal" required>
                    <input type="number" step="0.01" name="subtotal" class="form-in @error('subtotal') is-invalid @enderror"
                           id="inv-subtotal" readonly
                           value="{{ old('subtotal') }}">
                </x-form-group>
                <x-form-group label="Tasse/IVA" name="tax_amount" required>
                    <input type="number" step="0.01" name="tax_amount" class="form-in @error('tax_amount') is-invalid @enderror"
                           value="{{ old('tax_amount') }}">
                </x-form-group>
                <x-form-group label="Valuta" name="currency">
                    <input name="currency" class="form-in @error('currency') is-invalid @enderror"
                           value="{{ old('currency', 'EUR') }}">
                </x-form-group>
            </div>

            {{-- Voci Fattura --}}
            <div x-data="invoiceItemsHandler(0)" class="inv-items-builder">
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
                <a href="{{ route('invoices.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva Fattura</button>
            </div>
        </form>
    </x-panel>


    @push('scripts')
    <script>
    function invoiceItemsHandler(linkedTotal) {
        return {
            lines: [],
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
            initProjectSelect('client_sel', 'project_sel', null);
        }

        const issueDateInput = document.getElementById('issue_date');
        const dueDateInput = document.getElementById('due_date');

        if (issueDateInput && dueDateInput) {
            issueDateInput.addEventListener('change', (e) => {
                if (e.target.value) {
                    const issueDate = new Date(e.target.value);
                    issueDate.setDate(issueDate.getDate() + 30);
                    dueDateInput.value = issueDate.toISOString().split('T')[0];
                }
            });
        }
    });
    </script>
    @endpush
</x-app-layout>