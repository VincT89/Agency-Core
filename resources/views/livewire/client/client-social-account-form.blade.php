<x-panel title="Dati Social / Meta" dot="var(--orange)" padded>
    <form wire:submit="save">
        <div class="form-g mb-3">
            <label class="form-lbl">Facebook Page URL</label>
            <input type="url" wire:model="facebook_page_url" class="t-input" placeholder="https://facebook.com/...">
        </div>
        
        <div class="form-g mb-3">
            <label class="form-lbl">Instagram Profile URL</label>
            <input type="url" wire:model="instagram_profile_url" class="t-input" placeholder="https://instagram.com/...">
        </div>
        
        <div class="form-g mb-3">
            <label class="form-lbl">Meta Business Manager ID</label>
            <input type="text" wire:model="meta_business_manager_id" class="t-input">
        </div>
        
        <div class="form-g mb-3">
            <label class="form-lbl" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" wire:model="has_agency_access">
                L'agenzia ha accesso come Partner?
            </label>
        </div>
        
        <div class="form-g mb-3">
            <label class="form-lbl">Stato Accesso</label>
            <select wire:model="access_status" class="t-select">
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        
        <div class="form-g mb-3">
            <label class="form-lbl">Note Operative</label>
            <textarea wire:model="notes" class="t-input" rows="3"></textarea>
        </div>
        
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                @if (session()->has('success'))
                    <span style="color:var(--green); font-size:13px;">{{ session('success') }}</span>
                @endif
            </div>
            <button type="submit" class="btn btn-g">Salva Dati Meta</button>
        </div>
    </form>
</x-panel>
