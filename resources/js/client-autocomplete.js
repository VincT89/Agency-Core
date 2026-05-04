export default function clientAutocomplete({ initialValue, initialText, canCreate, searchEndpoint, storeEndpoint }) {
    return {
        value: initialValue || '',
        search: initialText || '',
        originalText: initialText || '',
        results: [],
        isOpen: false,
        loading: false,
        canCreate: canCreate,
        showQuickCreate: false,
        newClient: {
            name: '',
            company_name: '',
            email: '',
            phone: '',
            vat_number: '',
            address: '',
            status: 'active'
        },
        errors: {},
        genericError: null,

        init() {
            this.$watch('search', (value) => {
                if (value !== this.originalText) {
                    this.value = '';
                    this.originalText = '';
                }
                
                if (value.length >= 1) {
                    this.newClient.name = value;
                }
            });

            this.$watch('value', (val) => {
                this.$el.dispatchEvent(new CustomEvent('client-updated', {
                    detail: val,
                    bubbles: true
                }));
            });
        },

        open() {
            if (this.search.length >= 1 || this.results.length > 0) {
                this.isOpen = true;
            }
        },

        close() {
            this.isOpen = false;
        },

        openQuickCreate() {
            this.showQuickCreate = true;
            this.isOpen = false;
        },

        async fetchResults() {
            if (this.search.length < 1) {
                this.results = [];
                this.isOpen = false;
                return;
            }

            this.loading = true;
            this.isOpen = true;
            this.errors = {};
            this.genericError = null;

            try {
                const response = await fetch(`${searchEndpoint}?q=${encodeURIComponent(this.search)}`);
                if (response.ok) {
                    this.results = await response.json();
                }
            } catch (error) {
                console.error("Errore ricerca clienti", error);
            } finally {
                this.loading = false;
            }
        },

        selectClient(client) {
            this.value = client.id;
            
            let displayText = client.name;
            if (client.company_name) {
                displayText += ` - ${client.company_name}`;
            }
            
            this.search = displayText;
            this.originalText = displayText;
            this.close();
        },

        async quickStoreClient() {
            this.loading = true;
            this.errors = {};
            this.genericError = null;

            // Prepare payload (removing empty strings so we don't send "" for nullable fields)
            const payload = {
                name: this.newClient.name
            };
            if (this.newClient.company_name) payload.company_name = this.newClient.company_name;
            if (this.newClient.email) payload.email = this.newClient.email;
            if (this.newClient.phone) payload.phone = this.newClient.phone;
            if (this.newClient.vat_number) payload.vat_number = this.newClient.vat_number;
            if (this.newClient.address) payload.address = this.newClient.address;

            try {
                const response = await fetch(storeEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (response.ok) {
                    this.selectClient(data);
                    
                    // Reset form
                    this.newClient = {
                        name: '',
                        company_name: '',
                        email: '',
                        phone: '',
                        vat_number: '',
                        address: ''
                    };
                    this.showQuickCreate = false;
                } else if (response.status === 422) {
                    // Laravel validation errors
                    for (const field in data.errors) {
                        this.errors[field] = data.errors[field][0];
                    }
                } else {
                    this.genericError = data.message || 'Errore durante la creazione';
                }
            } catch (error) {
                console.error("Errore salvataggio cliente", error);
                this.genericError = 'Errore di connessione';
            } finally {
                this.loading = false;
            }
        }
    };
}
