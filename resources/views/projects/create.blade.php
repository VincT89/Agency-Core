<x-app-layout title="Nuovo Progetto">
    <x-page-header
        eyebrow="Modulo · Core"
        
    >
    <x-slot:title><strong>Nuovo</strong> progetto</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('projects.index') }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('projects.store') }}" method="POST">
            @csrf
            <div class="u-text-sm u-text-muted u-mb-md">
                <i data-lucide="info" class="u-icon-sm"></i>
                I campi non contrassegnati da <b>*</b> sono opzionali.
            </div>

            <div class="sec-lbl">Dati Progetto</div>
            <div class="form-row">
                <x-form-group label="Nome Progetto" name="name" required>
                    <input name="name" class="form-in @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" placeholder="Es. Restyling E-commerce">
                </x-form-group>
                <x-form-group label="Codice Progetto" name="code">
                    <input name="code" class="form-in @error('code') is-invalid @enderror"
                           value="{{ old('code') }}" placeholder="Es. PRJ-001">
                </x-form-group>
            </div>

            <div class="form-row full u-mb-md">
                <x-form-group label="Team di Commessa" name="members" required>
                    <div class="u-flex u-flex-wrap u-gap-md" style="row-gap: 8px;">
                        @foreach($users as $user)
                            <label class="team-member-pill">
                                <input
                                    type="checkbox"
                                    name="members[]"
                                    value="{{ $user->id }}"
                                    {{ in_array($user->id, old('members', [])) ? 'checked' : '' }}
                                >
                                <span>{{ $user->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </x-form-group>
            </div>

            <div class="form-row">
                @php
                    $oldClientId = old('client_id');
                    $oldClientText = '';
                    if ($oldClientId) {
                        $oldClient = \App\Models\Client::find($oldClientId);
                        $oldClientText = $oldClient ? $oldClient->name . ($oldClient->company_name ? ' - ' . $oldClient->company_name : '') : '';
                    }
                @endphp
                <x-form-group label="Cliente" name="client_id" required>
                    <x-client-autocomplete 
                        name="client_id" 
                        :value="$oldClientId" 
                        :text="$oldClientText" 
                        :required="true" 
                    />
                </x-form-group>
                <x-form-group label="Stato" name="status">
                    <select name="status" class="form-sel @error('status') is-invalid @enderror">
                        <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Attivo</option>
                        <option value="completed" {{ old('status') == 'completed' ? 'selected' : '' }}>Completato</option>
                        <option value="on_hold" {{ old('status') == 'on_hold' ? 'selected' : '' }}>Sospeso</option>
                        <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>Annullato</option>
                    </select>
                </x-form-group>
            </div>
            <div class="sec-lbl u-mt-md">Tempi e Note</div>
            <div class="form-row">
                <x-form-group label="Data di Avvio" name="start_date">
                    <input type="date" name="start_date" class="form-in @error('start_date') is-invalid @enderror"
                           value="{{ old('start_date') }}">
                </x-form-group>
                <x-form-group label="Data Prevista Fine" name="end_date">
                    <input type="date" name="end_date" class="form-in @error('end_date') is-invalid @enderror"
                           value="{{ old('end_date') }}">
                </x-form-group>
            </div>
            
            <div class="form-row full">
                <x-form-group label="Descrizione / Note operative" name="description">
                    <textarea name="description" class="form-ta @error('description') is-invalid @enderror" rows="3" placeholder="Info aggiuntive...">{{ old('description') }}</textarea>
                </x-form-group>
            </div>

            <div class="modal-ft u-section-sep">
                <a href="{{ route('projects.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva Progetto</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>