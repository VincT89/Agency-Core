<x-app-layout title="Modifica Progetto">
    <x-page-header
        eyebrow="Modulo · Core"
        
    >
    <x-slot:title><strong>Modifica</strong> progetto</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('projects.show', $project) }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('projects.update', $project) }}" method="POST">
            @csrf
            @method('PATCH')
            <div style="margin-bottom:16px;font-size:12px;color:var(--text3);">
                <i data-lucide="info" style="width:14px;height:14px;display:inline-block;vertical-align:text-bottom;margin-right:4px;"></i>
                I campi non contrassegnati da <b>*</b> sono opzionali.
            </div>

            <div class="sec-lbl">Dati Progetto</div>
            <div class="form-row">
                <x-form-group label="Nome Progetto" name="name" required>
                    <input name="name" class="form-in @error('name') is-invalid @enderror"
                           value="{{ old('name', $project->name) }}">
                </x-form-group>
                <x-form-group label="Codice Progetto" name="code">
                    <input name="code" class="form-in @error('code') is-invalid @enderror"
                           value="{{ old('code', $project->code) }}">
                </x-form-group>
            </div>

            <div class="form-row">
                @php
                    $currentClientId = old('client_id', $project->client_id);
                    $currentClientText = '';
                    if ($currentClientId) {
                        $currentClient = \App\Models\Client::find($currentClientId);
                        $currentClientText = $currentClient ? $currentClient->name . ($currentClient->company_name ? ' - ' . $currentClient->company_name : '') : '';
                    }
                @endphp
                <x-form-group label="Cliente" name="client_id" required>
                    <x-client-autocomplete 
                        name="client_id" 
                        :value="$currentClientId" 
                        :text="$currentClientText" 
                        :required="true" 
                    />
                </x-form-group>
                <x-form-group label="Stato" name="status">
                    <select name="status" class="form-sel @error('status') is-invalid @enderror">
                        <option value="active" {{ old('status', $project->status) == 'active' ? 'selected' : '' }}>Attivo</option>
                        <option value="completed" {{ old('status', $project->status) == 'completed' ? 'selected' : '' }}>Completato</option>
                        <option value="on_hold" {{ old('status', $project->status) == 'on_hold' ? 'selected' : '' }}>Sospeso</option>
                        <option value="cancelled" {{ old('status', $project->status) == 'cancelled' ? 'selected' : '' }}>Annullato</option>
                    </select>
                </x-form-group>
            </div>
            <div class="sec-lbl" style="margin-top:16px;">Tempi e Note</div>
            <div class="form-row">
                <x-form-group label="Data di Avvio" name="start_date">
                    <input type="date" name="start_date" class="form-in @error('start_date') is-invalid @enderror"
                           value="{{ old('start_date', $project->start_date?->toDateString()) }}">
                </x-form-group>
                <x-form-group label="Data Prevista Fine" name="end_date">
                    <input type="date" name="end_date" class="form-in @error('end_date') is-invalid @enderror"
                           value="{{ old('end_date', $project->end_date?->toDateString()) }}">
                </x-form-group>
            </div>

            <div class="form-row full">
                <x-form-group label="Descrizione / Note operative" name="description">
                    <textarea name="description" class="form-ta @error('description') is-invalid @enderror" rows="3">{{ old('description', $project->description) }}</textarea>
                </x-form-group>
            </div>

            <div class="modal-ft" style="border-top:1px solid var(--line);padding-top:16px;margin-top:16px">
                <a href="{{ route('projects.show', $project) }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Aggiorna Progetto</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>