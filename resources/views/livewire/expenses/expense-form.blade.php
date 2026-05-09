<div>
    <x-page-header 
        eyebrow="Amministrazione · Spese" 
        meta="{{ $expense ? 'Modifica la spesa selezionata' : 'Registra una nuova spesa operativa' }}">
        <x-slot:title><strong>{{ $expense ? 'Modifica' : 'Nuova' }}</strong> spesa</x-slot:title>
        <x-slot:actions>
            <a href="{{ $expense ? route('expenses.show', $expense) : route('expenses.index') }}" wire:navigate class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form wire:submit="save">
            
            <div class="form-row full">
                <x-form-group label="Titolo Spesa" name="title" required>
                    <input type="text" wire:model="title" class="form-in @error('title') is-invalid @enderror" placeholder="Es. Licenza Software, Abbonamento, ecc.">
                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Importo (€)" name="amount" required>
                    <input type="number" step="0.01" min="0" wire:model="amount" class="form-in @error('amount') is-invalid @enderror" placeholder="0.00">
                    @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </x-form-group>
                <x-form-group label="Stato" name="status" required>
                    <select wire:model="status" class="form-sel @error('status') is-invalid @enderror">
                        <option value="pending">Da Pagare</option>
                        <option value="paid">Pagata</option>
                        <option value="cancelled">Annullata</option>
                    </select>
                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Categoria" name="category">
                    <input type="text" wire:model="category" class="form-in @error('category') is-invalid @enderror" placeholder="Es. Software, Cancelleria...">
                    @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </x-form-group>
                <x-form-group label="Fornitore" name="supplier">
                    <input type="text" wire:model="supplier" class="form-in @error('supplier') is-invalid @enderror" placeholder="Es. Adobe, Aruba...">
                    @error('supplier') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Data Spesa" name="expense_date" required>
                    <input type="date" wire:model="expense_date" class="form-in @error('expense_date') is-invalid @enderror">
                    @error('expense_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </x-form-group>
                <x-form-group label="Data Scadenza" name="due_date">
                    <input type="date" wire:model="due_date" class="form-in @error('due_date') is-invalid @enderror">
                    <div class="u-text-meta u-mt-xs">Opzionale. Usata per calcolare i ritardi di pagamento.</div>
                    @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </x-form-group>
            </div>

            <div class="form-row full">
                <x-form-group label="Descrizione Breve" name="description">
                    <textarea wire:model="description" class="form-ta @error('description') is-invalid @enderror" rows="2" placeholder="Dettagli opzionali sulla spesa..."></textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </x-form-group>
            </div>

            <div class="u-section-sep u-mt-md u-mb-md"></div>
            <div class="ticket-create-note u-mb-md">
                <strong>Imputazione Costo (Opzionale):</strong> Puoi collegare questa spesa a un cliente, progetto, ticket o task specifico per tracciare accuratamente i costi operativi associati.
            </div>

            <div class="form-row">
                <x-form-group label="Tipo Collegamento" name="expenseable_type">
                    <select wire:model.live="expenseable_type" class="form-sel @error('expenseable_type') is-invalid @enderror">
                        <option value="">Nessun collegamento</option>
                        <option value="client">Cliente</option>
                        <option value="project">Progetto</option>
                        <option value="ticket">Ticket</option>
                        <option value="task">Task</option>
                        <option value="hosting_service">Servizio Hosting/Dominio</option>
                    </select>
                    @error('expenseable_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </x-form-group>

                @if($expenseable_type)
                <x-form-group label="Seleziona Elemento" name="expenseable_id" required>
                    <select wire:model="expenseable_id" class="form-sel @error('expenseable_id') is-invalid @enderror">
                        <option value="">-- Seleziona --</option>
                        @foreach($expenseableOptions as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    @error('expenseable_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </x-form-group>
                @else
                <div class="form-group"></div>
                @endif
            </div>

            <div class="u-section-sep u-mt-md u-mb-md"></div>

            <div class="form-row full">
                <x-form-group label="Note Interne" name="notes">
                    <textarea wire:model="notes" class="form-ta @error('notes') is-invalid @enderror" rows="3" placeholder="Note interne visibili solo al team..."></textarea>
                    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </x-form-group>
            </div>

            <div class="modal-ft u-section-sep">
                <a href="{{ $expense ? route('expenses.show', $expense) : route('expenses.index') }}" wire:navigate class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva spesa</button>
            </div>
            
        </form>
    </x-panel>
</div>
