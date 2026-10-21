export function financeLookup({ endpoint, type, value = '', text = '', client = '', serviceType = '', freeText = false }) {
    return {
        value: value || '', text: text || '', original: text || '', options: [], open: false,
        loading: false, error: '', request: 0, client, ignoreFocus: false, optionsStyle: {},
        onFocus() { if (!this.ignoreFocus) this.search(); },
        place() {
            if (!this.open) return;
            const box = this.$el.getBoundingClientRect();
            const height = Math.min(240, this.$refs.options.scrollHeight || 240);
            const below = window.innerHeight - box.bottom - 12;
            const above = box.top - 60;
            this.optionsStyle = below < height && above > below
                ? { top: 'auto', bottom: '100%', maxHeight: Math.max(80, Math.min(240, above)) + 'px' }
                : { top: '100%', bottom: 'auto', maxHeight: Math.max(80, Math.min(240, below)) + 'px' };
        },
        invalidate() {
            if (this.text !== this.original) this.value = '';
            this.validity();
        },
        validity() {
            this.$refs.search?.setCustomValidity(!freeText && this.text && !this.value ? 'Seleziona un risultato oppure azzera la ricerca.' : '');
        },
        async search(all = false) {
            if (this.text !== this.original) this.value = '';
            const request = ++this.request;
            this.loading = true;
            this.error = '';
            this.open = true;
            this.$nextTick(() => this.place());
            try {
                const params = new URLSearchParams({ type, q: all || (!freeText && this.value && this.text === this.original) ? '' : this.text, client_id: this.client || '', service_type: serviceType });
                const response = await fetch(`${endpoint}?${params}`, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error();
                const options = await response.json();
                if (request !== this.request) return;
                this.options = options;
                this.open = true;
                this.$nextTick(() => this.place());
            } catch {
                if (request === this.request) this.error = 'Ricerca non disponibile. Riprova.';
            } finally {
                if (request === this.request) this.loading = false;
            }
        },
        select(option) {
            ++this.request;
            this.value = option.id;
            this.text = this.original = option.name;
            this.open = false;
            this.validity();
            if (!freeText) this.$dispatch('finance-selected', { type, id: option.id });
            if (freeText) this.$nextTick(() => this.$el.closest('form')?.requestSubmit());
        },
        async firstOption() {
            if (!this.open) await this.search();
            this.$nextTick(() => this.$refs.options.querySelector('button')?.focus());
        },
        close(restoreFocus = false) {
            ++this.request;
            this.loading = false;
            this.open = false;
            if (restoreFocus) {
                this.ignoreFocus = true;
                this.$refs.search.focus({ preventScroll: true });
                this.ignoreFocus = false;
            }
        },
        destroy() { this.close(); },
        clear() {
            ++this.request;
            this.value = this.text = this.original = '';
            this.options = [];
            this.open = false;
            this.validity();
            if (!freeText) this.$dispatch('finance-selected', { type, id: '' });
            if (freeText) this.$nextTick(() => this.$el.closest('form')?.requestSubmit());
        },
    };
}

export function financeChart(data, options, drillUrl, display = 'line') {
    return {
        chart: null,
        init() {
            if (!window.ApexCharts) {
                this.timer = setTimeout(() => this.init(), 150);
                return;
            }
            const money = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
            const values = options.series.flatMap(series => series.data).filter(value => value !== null);
            const drill = (_, __, opts) => {
                        const period = data.periods[opts.dataPointIndex];
                        if (!period || opts.seriesIndex !== 0) return;
                        const url = new URL(drillUrl, location.origin);
                        const currentFrom = url.searchParams.get('from');
                        const currentTo = url.searchParams.get('to');
                        url.searchParams.set('from', currentFrom && currentFrom > period.from ? currentFrom : period.from);
                        url.searchParams.set('to', currentTo && currentTo < period.to ? currentTo : period.to);
                        url.searchParams.delete('page');
                        url.searchParams.delete('documents_page');
                        url.hash = 'report-documents';
                        location.assign(url);
                    };
            this.chart = new window.ApexCharts(this.$refs.chart, {
                ...options,
                chart: { ...options.chart, background: 'transparent', type: display, toolbar: { show: true, tools: { download: false, selection: true, zoom: true, zoomin: true, zoomout: true, pan: true, reset: true } },
                    animations: { enabled: !matchMedia('(prefers-reduced-motion: reduce)').matches, speed: 240 },
                    events: display === 'line' ? { markerClick: drill } : { dataPointSelection: drill } },
                stroke: { ...options.stroke, width: display === 'bar' ? 0 : 3, dashArray: [0, 5] },
                plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
                fill: { type: 'solid', opacity: 1 },
                markers: { size: display === 'bar' ? 0 : 4 }, dataLabels: { enabled: false },
                xaxis: { ...options.xaxis, labels: { hideOverlappingLabels: true, rotate: -45 } },
                yaxis: { labels: { formatter: value => money.format(value) }, forceNiceScale: true,
                    ...(values.every(value => value >= 0) ? { min: 0 } : {}),
                    ...(values.every(value => value <= 0) && values.some(value => value < 0) ? { max: 0 } : {}) },
                tooltip: { y: { formatter: value => money.format(value) } },
                grid: { borderColor: '#dfe3ea', strokeDashArray: 4 },
                noData: { text: 'Nessun dato nel periodo' },
            });
            this.chart.render();
        },
        destroy() {
            clearTimeout(this.timer);
            this.chart?.destroy();
        },
    };
}

export function financeDistribution(options, entries, kind) {
    return {
        chart: null,
        init() {
            if (!entries.length) return;
            if (!window.ApexCharts) {
                this.timer = setTimeout(() => this.init(), 150);
                return;
            }
            const money = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
            this.chart = new window.ApexCharts(this.$refs.chart, {
                ...options,
                chart: { ...options.chart, background: 'transparent', toolbar: { show: false },
                    animations: { enabled: !matchMedia('(prefers-reduced-motion: reduce)').matches, speed: 240 },
                    events: { dataPointSelection: (_, __, selected) => {
                        const entry = entries[kind === 'expenses' ? selected.seriesIndex : selected.dataPointIndex];
                        if (entry?.url) location.assign(entry.url);
                    } } },
                stroke: { width: kind === 'expenses' ? 3 : 0 },
                fill: { type: 'solid', opacity: 1 },
                plotOptions: kind === 'expenses'
                    ? { pie: { donut: { size: '72%', labels: { show: true, name: { show: false },
                        value: { formatter: value => money.format(Number(value)) },
                        total: { show: true, showAlways: true, label: 'Spese', formatter: () => money.format(entries.reduce((sum, entry) => sum + entry.amount, 0)) } } } } }
                    : { bar: { horizontal: true, borderRadius: 4, barHeight: '55%' } },
                xaxis: kind === 'expenses' ? options.xaxis : { ...options.xaxis, labels: { formatter: value => money.format(Number(value)) } },
                yaxis: kind === 'expenses' ? options.yaxis : { labels: { maxWidth: 180, formatter: value => String(value).length > 24 ? String(value).slice(0, 22) + '…' : String(value) } },
                legend: { ...options.legend, position: 'bottom', fontSize: '12px' },
                tooltip: { y: { formatter: value => money.format(value) } },
                grid: { borderColor: '#dfe3ea', strokeDashArray: 4 },
            });
            this.chart.render();
        },
        destroy() { clearTimeout(this.timer); this.chart?.destroy(); },
    };
}
