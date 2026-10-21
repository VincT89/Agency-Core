export function financeRegion(region) {
    return {
        busy: false, error: '', request: 0, timer: null, controller: null, path: '',
        init() { this.path = location.pathname; },
        submit(event) {
            if (!event.target.matches('[data-finance-form]')) return;
            event.preventDefault();
            this.submitForm(event.target);
        },
        submitForm(form, replace = false, focusId = '') {
            clearTimeout(this.timer);
            const url = new URL(form.action, location.origin);
            for (const [name, value] of new FormData(form)) {
                if (String(value).trim() !== '') url.searchParams.set(name, value);
            }
            this.navigate(url, replace ? 'replace' : 'push', focusId);
        },
        follow(event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const link = event.target.closest('a[data-finance-link], [data-finance-pagination] a');
            if (!link) return;
            event.preventDefault();
            this.navigate(link.href);
        },
        type(event) {
            if (!event.target.matches('[data-finance-live]')) return;
            clearTimeout(this.timer);
            this.controller?.abort();
            ++this.request;
            const form = event.target.form;
            this.timer = setTimeout(() => this.submitForm(form, true), 350);
        },
        change(event) {
            if (event.target.matches('[data-finance-auto]')) this.submitForm(event.target.form);
        },
        async navigate(target, historyMode = 'push', focusId = '') {
            const url = new URL(target, location.origin);
            if (url.origin !== location.origin || url.pathname !== this.path) return;
            this.controller?.abort();
            this.controller = new AbortController();
            const request = ++this.request;
            this.busy = true;
            this.error = '';
            try {
                const response = await fetch(url, { signal: this.controller.signal, cache: 'no-store',
                    headers: { Accept: 'application/json', 'X-Finance-Partial': region } });
                if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
                    throw new Error('Sessione scaduta. Accedi nuovamente per continuare.');
                }
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || 'Impossibile aggiornare i risultati. Riprova.');
                if (typeof data.html !== 'string' || request !== this.request) return;
                const active = document.activeElement;
                const focusedId = focusId || (this.$el.contains(active) ? active.id : '');
                const selection = active?.matches('input[type=search], input[type=text]') ? [active.selectionStart, active.selectionEnd] : null;
                const scroll = [window.scrollX, window.scrollY];
                const table = this.$refs.content.querySelector('.t-table-wrap');
                const scrollLeft = table?.scrollLeft || 0;
                /** Render only escaped server templates; stale requests never replace newer filter results. */
                window.Alpine.mutateDom(() => {
                    [...this.$refs.content.children].forEach(element => window.Alpine.destroyTree(element));
                    this.$refs.content.innerHTML = data.html;
                    [...this.$refs.content.children].forEach(element => window.Alpine.initTree(element));
                });
                if (Number.isInteger(data.document_count)) {
                    const count = document.getElementById('invoice-document-count');
                    if (count) count.textContent = data.document_count + ' documenti';
                }
                if (historyMode !== 'none') history[historyMode === 'replace' ? 'replaceState' : 'pushState']({}, '', url);
                this.$nextTick(() => {
                    const field = focusedId ? document.getElementById(focusedId) : null;
                    field?.focus({ preventScroll: true });
                    if (selection && field?.setSelectionRange) field.setSelectionRange(...selection);
                    const nextTable = this.$refs.content.querySelector('.t-table-wrap');
                    if (nextTable) nextTable.scrollLeft = scrollLeft;
                    window.scrollTo(...scroll);
                });
            } catch (error) {
                if (error.name !== 'AbortError' && request === this.request) this.error = error.message;
            } finally {
                if (request === this.request) this.busy = false;
            }
        },
        restore() { if (location.pathname === this.path) this.navigate(location.href, 'none'); },
        destroy() { clearTimeout(this.timer); this.controller?.abort(); },
    };
}

export function invoiceColumnFilter(id) {
    return {
        open: false, position: {},
        toggle() {
            if (this.open) return this.close();
            window.dispatchEvent(new CustomEvent('invoice-filter-open', { detail: id }));
            this.open = true;
            this.$nextTick(() => { this.place(); this.$refs.menu.querySelector('a, input, select')?.focus({ preventScroll: true }); });
        },
        place() {
            if (!this.open) return;
            const box = this.$refs.trigger.getBoundingClientRect();
            const width = Math.min(320, window.innerWidth - 24);
            const height = Math.min(this.$refs.menu.scrollHeight || 360, window.innerHeight - 24);
            const below = window.innerHeight - box.bottom - 12;
            const above = box.top - 12;
            const top = below < height && above > below ? Math.max(12, box.top - height - 6) : Math.max(12, Math.min(box.bottom + 6, window.innerHeight - height - 12));
            this.position = { top: Math.max(12, top) + 'px', left: Math.max(12, Math.min(box.left, window.innerWidth - width - 12)) + 'px',
                width: width + 'px', maxHeight: Math.max(150, window.innerHeight - top - 12) + 'px' };
        },
        close() { this.open = false; },
        choose(url) {
            this.close();
            this.$dispatch('finance-navigate', { region: 'invoice-list', url, focusId: id });
        },
        apply(form) {
            this.close();
            this.$dispatch('finance-submit', { region: 'invoice-list', form, focusId: id });
        },
    };
}
