export default function quoteEditor(options) {
    return {
        items: [], introduction: options.introduction || '', instructions: options.instructions || '',
        libraryQuery: '', libraryResults: [], libraryLoading: false, libraryRequest: 0, libraryOpen: false, libraryLoadedQuery: null,
        message: '', error: '', savingService: null, aiBusy: false, suggestion: null, suggestionSource: '',
        init() { this.items = options.items.map(item => this.row(item)); },
        row(item = {}) {
            return { name: '', summary: '', description: '', delivery_summary: '', delivery_terms: '', quantity: '1', unit_price: '', ...item,
                key: crypto.randomUUID(), expanded: Boolean(options.hasErrors) };
        },
        focusItem(item) {
            const root = this.$root;
            this.$nextTick(() => {
                if (root?.isConnected) root.querySelector(`[data-service-key="${item.key}"] input`)?.focus();
            });
        },
        add() {
            if (this.items.length >= 50) return;
            const item = this.row();
            this.items.push(item); this.libraryOpen = false; this.focusItem(item);
        },
        duplicate(index) {
            if (this.items.length >= 50) return;
            const item = this.row(this.items[index]);
            this.items.splice(index + 1, 0, item); this.focusItem(item);
        },
        remove(index) {
            if (this.items.length <= 1) return;
            this.items.splice(index, 1);
            this.focusItem(this.items[Math.min(index, this.items.length - 1)]);
        },
        revealInvalid(field) {
            for (let parent = field.parentElement; parent && parent !== this.$root; parent = parent.parentElement) {
                if (parent.tagName === 'DETAILS') parent.open = true;
            }
        },
        move(index, direction) {
            const target = index + direction;
            if (target >= 0 && target < this.items.length) {
                const rows = [...this.items];
                [rows[index], rows[target]] = [rows[target], rows[index]];
                this.items = rows;
            }
        },
        cents(value) {
            const match = String(value).match(/^(\d+)(?:\.(\d{1,2}))?$/);
            return match ? Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0')) : 0;
        },
        lineCents(item) { return Math.floor((this.cents(item.quantity) * this.cents(item.unit_price) + 50) / 100); },
        money(cents) { return (cents / 100).toLocaleString('it-IT', { style: 'currency', currency: 'EUR' }); },
        total() { return this.money(this.items.reduce((sum, item) => sum + this.lineCents(item), 0)); },
        async request(url, data) {
            const response = await fetch(url, { method: 'POST', headers: {
                'Accept': 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }, body: JSON.stringify(data) });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || (response.status === 419
                ? 'La sessione è scaduta. Copia i testi prima di ricaricare la pagina.'
                : response.status === 429 ? 'Troppe richieste. Attendi un minuto prima di riprovare.' : 'Operazione non riuscita. Riprova.'));
            return result;
        },
        openLibrary() {
            this.libraryOpen = true;
            if (this.libraryLoadedQuery !== this.libraryQuery) this.searchLibrary();
        },
        async chooseFirstService() {
            if (!this.libraryOpen) { this.openLibrary(); return; }
            if (this.libraryLoading) return;
            if (this.libraryLoadedQuery !== this.libraryQuery) await this.searchLibrary();
            if (this.libraryResults.length === 1) this.useService(this.libraryResults[0]);
            else this.$refs.libraryResults.querySelector('button')?.focus();
        },
        async searchLibrary() {
            const request = ++this.libraryRequest;
            const query = this.libraryQuery;
            this.libraryLoading = true; this.error = '';
            try {
                const response = await fetch(`${options.libraryUrl}?q=${encodeURIComponent(query)}`, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error('La libreria non è disponibile. Riprova.');
                const results = await response.json();
                if (request === this.libraryRequest) { this.libraryResults = results; this.libraryLoadedQuery = query; }
            } catch (error) { if (request === this.libraryRequest) { this.error = error.message; this.libraryResults = []; } }
            finally { if (request === this.libraryRequest) this.libraryLoading = false; }
        },
        useService(service) {
            if (this.items.length >= 50) return;
            const item = Object.fromEntries(['name', 'summary', 'description', 'delivery_summary', 'delivery_terms', 'quantity', 'unit_price'].map(field => [field, service[field] ?? '']));
            const empty = this.items.length === 1 && ['name', 'summary', 'description', 'delivery_summary', 'delivery_terms', 'unit_price'].every(field => !this.items[0][field]) && Number(this.items[0].quantity) === 1;
            if (empty) this.items = [this.row(item)];
            else this.items.push(this.row(item));
            this.message = `${service.name}: servizio inserito con testi e prezzo.`;
        },
        async saveService(item) {
            const invalid = this.$root.querySelector(`[data-service-key="${item.key}"] :invalid`);
            if (invalid) { this.revealInvalid(invalid); invalid.reportValidity(); return; }
            this.error = ''; this.message = ''; this.savingService = item.key;
            try {
                const payload = Object.fromEntries(['name', 'summary', 'description', 'delivery_summary', 'delivery_terms', 'quantity', 'unit_price'].map(field => [field, item[field]]));
                const result = await this.request(options.libraryUrl, payload);
                this.message = result.message;
                this.libraryLoadedQuery = null;
            } catch (error) { this.error = error.message; }
            finally { this.savingService = null; }
        },
        aiInput() { return { introduction: this.introduction, instructions: this.instructions,
            items: this.items.map(({ name, summary, description }) => ({ name, summary, description })) }; },
        aiSource() { return JSON.stringify({ input: this.aiInput(), keys: this.items.map(item => item.key) }); },
        async improve() {
            this.error = ''; this.message = ''; this.suggestion = null; this.aiBusy = true;
            const source = this.aiSource();
            try {
                const result = await this.request(options.aiUrl, this.aiInput());
                if (source !== this.aiSource()) throw new Error('Hai modificato i testi durante la richiesta. Genera una nuova proposta AI.');
                this.suggestionSource = source; this.suggestion = result;
                this.$nextTick(() => this.$refs.aiPreview.focus());
            } catch (error) { this.error = error.message; }
            finally { this.aiBusy = false; }
        },
        applySuggestion() {
            if (!this.suggestion || this.suggestionSource !== this.aiSource()) {
                this.error = 'Il contenuto è cambiato dopo l’anteprima. Genera una nuova proposta AI.'; this.suggestion = null; return;
            }
            this.introduction = this.suggestion.introduction;
            this.suggestion.items.forEach((item, index) => {
                this.items[index].summary = item.summary; this.items[index].description = item.description;
            });
            this.suggestion = null; this.message = 'Testi applicati. Salva la bozza per conservarli.';
        },
    };
}
