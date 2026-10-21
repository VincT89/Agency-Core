export default function expenseCategoryMenu() {
    return {
        open: false, loading: false, error: '', activeId: '', position: {}, request: 0,
        toggle(id) {
            if (this.open && this.activeId === id) return this.close(true);
            return this.show(id);
        },
        async show(id) {
            const request = ++this.request;
            this.open = false;
            this.activeId = id;
            this.loading = true;
            this.error = '';
            try {
                if (String(this.$wire.category_id) !== id) await this.$wire.selectCategory(id);
                if (request !== this.request || String(this.$wire.category_id) !== id) return;
                this.open = true;
                this.$nextTick(() => {
                    this.place();
                    const selected = this.$refs.options.querySelector('[aria-selected=true]');
                    (selected || this.$refs.options.querySelector('button'))?.focus({ preventScroll: true });
                });
            } catch {
                this.error = 'Impossibile aprire le sottocategorie. Riprova.';
            } finally {
                if (request === this.request) this.loading = false;
            }
        },
        place() {
            if (!this.open) return;
            const trigger = document.getElementById('expense-category-' + this.activeId);
            if (!trigger) return this.close();
            const box = trigger.getBoundingClientRect();
            const width = Math.min(340, window.innerWidth - 24);
            const height = Math.min(320, this.$refs.options.scrollHeight || 320, window.innerHeight - 24);
            const below = window.innerHeight - box.bottom - 12;
            const top = below < height && box.top > below
                ? Math.max(12, box.top - height - 6)
                : Math.max(12, Math.min(box.bottom + 6, window.innerHeight - height - 12));
            this.position = { top: top + 'px', left: Math.max(12, Math.min(box.left, window.innerWidth - width - 12)) + 'px',
                width: width + 'px', maxHeight: Math.min(320, window.innerHeight - top - 12) + 'px' };
        },
        async select(id) {
            if (this.loading) return;
            this.loading = true;
            this.error = '';
            try {
                await this.$wire.selectSubcategory(id);
                this.close(true);
            } catch {
                this.error = 'Impossibile filtrare le spese. Riprova.';
            } finally {
                this.loading = false;
            }
        },
        async reset() {
            this.close();
            this.loading = true;
            this.error = '';
            try { await this.$wire.selectCategory(''); }
            catch { this.error = 'Impossibile azzerare la categoria. Riprova.'; }
            finally { this.loading = false; }
        },
        move(direction) {
            const options = [...this.$refs.options.querySelectorAll('button:not(:disabled)')];
            const index = options.indexOf(document.activeElement);
            options[(index + direction + options.length) % options.length]?.focus();
        },
        edge(last) {
            const options = this.$refs.options.querySelectorAll('button:not(:disabled)');
            options[last ? options.length - 1 : 0]?.focus();
        },
        close(restoreFocus = false) {
            this.open = false;
            if (restoreFocus) this.$nextTick(() => document.getElementById('expense-category-' + this.activeId)?.focus({ preventScroll: true }));
        },
        destroy() { ++this.request; },
    };
}
