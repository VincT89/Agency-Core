<x-app-layout title="Nuovo Ticket">
    <x-page-header
        eyebrow="Modulo · Operativo"
        
    >
    <x-slot:title><strong>Nuovo</strong> ticket</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('tickets.index') }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <div style="font-size:14px;color:var(--text3);margin-bottom:24px;border-bottom:1px solid var(--line);padding-bottom:16px;">
            <strong>Nota:</strong> Usa questo modulo per tracciare guasti, richieste di supporto o comunicazioni dirette dal cliente.<br>
            Per la pianificazione esecutiva dei lavori interni, usa il modulo Task.
        </div>
        <form action="{{ route('tickets.store') }}" method="POST">
            @csrf
            
            <div class="form-row full">
                <x-form-group label="Oggetto" name="title" required>
                    <input name="title" class="form-in @error('title') is-invalid @enderror"
                           value="{{ old('title') }}" placeholder="Descrizione sintetica...">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Cliente" name="client_id">
                    <select name="client_id" id="client_sel" class="form-sel @error('client_id') is-invalid @enderror" required>
                        <option value="">Seleziona cliente (Obbligatorio)...</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Progetto" name="project_id">
                    <select name="project_id" id="project_sel" class="form-sel @error('project_id') is-invalid @enderror">
                        <option value="">Nessun progetto specifico...</option>
                    </select>
                    <div style="font-size:11px;color:var(--text3);margin-top:4px">Opzionale. Se selezionato, deve appartenere al cliente indicato.</div>
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Tipo" name="type" required>
                    <select name="type" class="form-sel @error('type') is-invalid @enderror">
                        @foreach($types as $t)
                            <option value="{{ $t }}" {{ old('type') == $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Priorità" name="priority" required>
                    <select name="priority" class="form-sel @error('priority') is-invalid @enderror">
                        @foreach($priorities as $p)
                            <option value="{{ $p }}" {{ old('priority', 'medium') == $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                        @endforeach
                    </select>
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Stato" name="status" required>
                    <select name="status" class="form-sel @error('status') is-invalid @enderror">
                        @foreach($statuses as $s)
                            <option value="{{ $s }}" {{ old('status', 'open') == $s ? 'selected' : '' }}>{{ (new \App\Models\Ticket(['status' => $s]))->status_label }}</option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Assegnato a" name="assigned_to">
                    <select name="assigned_to" class="form-sel @error('assigned_to') is-invalid @enderror">
                        <option value="">Nessuno</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ old('assigned_to') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                </x-form-group>
            </div>

            <div class="form-row full">
                <x-form-group label="Dettagli" name="description">
                    <textarea name="description" class="form-ta @error('description') is-invalid @enderror" rows="4" placeholder="Descrizione estesa del problema o richiesta...">{{ old('description') }}</textarea>
                </x-form-group>
            </div>

            <div class="modal-ft" style="border-top:1px solid var(--line);padding-top:16px;margin-top:16px">
                <a href="{{ route('tickets.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Crea ticket</button>
            </div>
        </form>
    </x-panel>


    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        if(typeof initProjectSelect !== 'undefined') {
            initProjectSelect('client_sel', 'project_sel', null);
        }
    });
    </script>
    @endpush
</x-app-layout>