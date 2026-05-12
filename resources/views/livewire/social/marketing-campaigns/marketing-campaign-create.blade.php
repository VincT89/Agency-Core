<div>
  <x-page-header eyebrow="Social">
    <x-slot:title><strong>Nuovo Progetto Marketing</strong></x-slot:title>
    <x-slot name="actions">
      <a href="{{ route('marketing-campaigns.index') }}" class="btn btn-s" wire:navigate>Annulla</a>
    </x-slot>
  </x-page-header>

  <x-panel class="mkt-panel-lg">
    <form wire:submit="save" class="mkt-form-col-gap24">
      
      <div class="form-g mb-0" @client-updated="$wire.set('client_id', $event.detail)">
        <label class="form-lbl">Cliente <span class="mkt-text-red">*</span></label>
        <div wire:ignore>
            <x-client-autocomplete 
                name="client_id" 
                :value="$client_id" 
                :required="true" 
            />
        </div>
        @error('client_id') <span class="form-err">{{ $message }}</span> @enderror
      </div>

      <div class="form-g mb-0">
        <label class="form-lbl">Nome Progetto <span class="mkt-text-red">*</span></label>
        <input type="text" class="form-in" wire:model="name" placeholder="Es: Progetto Invernale 2026" required>
        @error('name') <span class="form-err">{{ $message }}</span> @enderror
      </div>

      <div class="form-g mb-0">
        <label class="form-lbl">Descrizione / Obiettivi</label>
        <textarea class="form-ta" wire:model="description" placeholder="Breve descrizione o obiettivi del progetto..."></textarea>
        @error('description') <span class="form-err">{{ $message }}</span> @enderror
      </div>

      <div class="mkt-flex-gap16">
        <div class="form-g mb-0 mkt-flex-1">
          <label class="form-lbl">Data Inizio</label>
          <input type="date" class="form-in" wire:model="starts_at">
          @error('starts_at') <span class="form-err">{{ $message }}</span> @enderror
        </div>
        <div class="form-g mb-0 mkt-flex-1">
          <label class="form-lbl">Data Fine</label>
          <input type="date" class="form-in" wire:model="ends_at">
          @error('ends_at') <span class="form-err">{{ $message }}</span> @enderror
        </div>
      </div>

      <div class="form-g mb-0">
        <label class="form-lbl">Canone Mensile / Budget (€)</label>
        <input type="number" step="0.01" class="form-in mkt-input-w200" wire:model="monthly_fee" placeholder="0.00">
        @error('monthly_fee') <span class="form-err">{{ $message }}</span> @enderror
      </div>

      <div class="form-g mb-0">
        <label class="form-lbl">Note Interne</label>
        <textarea class="form-ta" wire:model="notes" placeholder="Note visibili solo al team..."></textarea>
        @error('notes') <span class="form-err">{{ $message }}</span> @enderror
      </div>

      <div class="mkt-form-footer">
        <button type="submit" class="btn btn-p">
          <span wire:loading.remove wire:target="save">Crea Progetto</span>
          <span wire:loading wire:target="save">Creazione in corso...</span>
        </button>
      </div>

    </form>
  </x-panel>
</div>
