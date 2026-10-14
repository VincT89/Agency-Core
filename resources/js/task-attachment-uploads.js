export default function taskAttachmentUploads(options) {
    return {
        items: [],
        busy: false,
        error: '',
        active: true,
        get summary() {
            if (!this.items.length) return '';
            const saved = this.items.filter(item => item.status === 'saved').length;
            const failed = this.items.filter(item => item.status === 'error').length;
            if (this.busy) return `Caricamento in corso. ${saved} di ${this.items.length} file salvati.`;
            return `${saved} di ${this.items.length} file caricati.${failed ? ` ${failed} non caricati: controlla i dettagli sotto.` : ''}`;
        },
        async select(fileList) {
            if (this.busy || !fileList.length) return;
            this.error = '';
            if (fileList.length > options.maxSelection) {
                this.error = `Seleziona al massimo ${options.maxSelection} file per volta.`;
                return;
            }
            this.items = Array.from(fileList, file => ({ file, name: file.name, status: 'waiting', progress: 0, error: '' }));
            this.busy = true;
            try {
                for (const item of this.items) {
                    if (!this.active) break;
                    const extension = item.name.includes('.') ? item.name.split('.').pop().toLowerCase() : '';
                    if (item.file.size > options.maxBytes) {
                        item.status = 'error';
                        item.error = `Il file supera il limite di ${options.maxMegabytes} MB.`;
                    } else if (!options.extensions.includes(extension)) {
                        item.status = 'error';
                        item.error = 'Formato non supportato. Scegli PDF, immagini, Word, Excel, CSV, ZIP o TXT.';
                    } else {
                        await this.transfer(item);
                    }
                    item.file = null;
                }
            } finally {
                this.busy = false;
            }
        },
        transfer(item) {
            item.status = 'uploading';
            return new Promise(resolve => {
                const fail = message => { item.status = 'error'; item.error = message; resolve(); };
                try {
                    this.$wire.$upload('file', item.file, async () => {
                        item.progress = 100;
                        item.status = 'saving';
                        try {
                            const result = await this.$wire.saveTaskAttachment();
                            if (result?.saved) {
                                item.status = 'saved';
                            } else {
                                item.status = 'error';
                                item.error = result?.message || 'Salvataggio non confermato. Controlla la tabella degli allegati prima di riprovare.';
                            }
                        } catch {
                            item.status = 'error';
                            item.error = 'Salvataggio non confermato. Controlla la tabella degli allegati prima di riprovare.';
                        }
                        resolve();
                    }, () => fail('Trasferimento non riuscito. Riprova; se il problema persiste, contatta l’amministratore.'),
                    event => { item.progress = Math.round(event.detail.progress); },
                    () => fail('Caricamento annullato.'));
                } catch {
                    fail('Non è stato possibile avviare il caricamento. Riprova.');
                }
            });
        },
        statusText(item) {
            return { waiting: 'In attesa', uploading: `Caricamento ${item.progress}%`, saving: 'Salvataggio...', saved: 'Caricato', error: 'Non caricato' }[item.status];
        },
        destroy() {
            this.active = false;
            if (this.busy) this.$wire.$cancelUpload('file');
        },
    };
}
