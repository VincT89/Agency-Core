export default function presenceControl(initial, stateUrl, updateUrl) {
    return {
        state: initial, busy: false, connected: navigator.onLine, error: '', stopped: false,
        timer: null, requestController: null, onOnline: null, onOffline: null, onVisible: null, deferPending: false,
        init() {
            this.onOnline = () => { this.connected = true; this.refresh(); };
            this.onOffline = () => { this.connected = false; this.error = 'Connessione assente. La disponibilità deve essere verificata al ritorno online.'; };
            this.onVisible = () => { if (!document.hidden) this.refresh(); };
            window.addEventListener('online', this.onOnline);
            window.addEventListener('offline', this.onOffline);
            document.addEventListener('visibilitychange', this.onVisible);
            this.syncLoginPrompt();
            this.schedule();
        },
        syncLoginPrompt() {
            this.$nextTick(() => {
                const dialog = this.$refs.loginPrompt;
                if (!dialog || this.stopped) return;
                if (this.state.login_prompt && !this.deferPending) {
                    if (!dialog.open) dialog.showModal();
                } else if (dialog.open) {
                    dialog.close();
                }
            });
        },
        async postpone() {
            this.deferPending = true;
            this.$refs.loginPrompt?.close();
            return this.act('defer');
        },
        trapLoginFocus(event) {
            const controls = [...this.$refs.loginPrompt.querySelectorAll('button:not([disabled]), a[href]')]
                .filter(element => element.getClientRects().length > 0);
            const first = controls[0], last = controls[controls.length - 1];
            if (!first) return event.preventDefault();
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
        async openAvailability(url) {
            await this.postpone();
            if (!this.deferPending) window.location.assign(url);
        },
        schedule() {
            clearTimeout(this.timer);
            if (!this.stopped) this.timer = setTimeout(() => this.refresh(), this.state.heartbeat_seconds * 1000);
        },
        refresh() {
            return this.act(this.deferPending ? 'defer' : (this.state.open && this.state.owned ? 'heartbeat' : null));
        },
        async act(action) {
            if (this.busy || this.stopped) return;
            clearTimeout(this.timer);
            this.busy = true;
            const controller = new AbortController();
            this.requestController = controller;
            const timeout = setTimeout(() => controller.abort(), 10000);
            try {
                const options = { method: action ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
                if (action) {
                    options.headers['Content-Type'] = 'application/json';
                    options.headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    options.body = JSON.stringify({ action, presence_id: this.state.session_id, availability_id: this.state.slot_id });
                }
                const response = await fetch(action ? updateUrl : stateUrl, options);
                if ([401, 419].includes(response.status) || response.redirected) {
                    this.stopped = true;
                    throw new Error('Sessione scaduta. Accedi nuovamente per confermare la disponibilità.');
                }
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || 'Impossibile verificare la disponibilità. Riprova.');
                this.state = data;
                if (action === 'defer') this.deferPending = false;
                this.syncLoginPrompt();
                this.connected = true;
                this.error = '';
            } catch (error) {
                if (!this.stopped || this.requestController === controller) {
                    this.connected = false;
                    this.error = error.name === 'AbortError' ? 'Collegamento da verificare. Riprovo automaticamente.' : error.message;
                }
            } finally {
                clearTimeout(timeout);
                this.busy = false;
                if (this.requestController === controller) this.requestController = null;
                this.schedule();
            }
        },
        destroy() {
            this.stopped = true;
            clearTimeout(this.timer);
            this.requestController?.abort();
            this.$refs.loginPrompt?.close();
            window.removeEventListener('online', this.onOnline);
            window.removeEventListener('offline', this.onOffline);
            document.removeEventListener('visibilitychange', this.onVisible);
        },
    };
}
