<x-app-layout title="Nuovo Evento">
    <x-page-header
        eyebrow="Modulo · Operativo"
        
    >
    <x-slot:title><strong>Nuovo</strong> evento</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('calendar-events.index') }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('calendar-events.store') }}" method="POST" x-data="{ eventType: '{{ old('type', 'other') }}' }">
            @csrf
            
            <div class="form-row full">
                <x-form-group label="Titolo Evento" name="title" required>
                    <input name="title" class="form-in @error('title') is-invalid @enderror"
                           value="{{ old('title') }}" placeholder="Es. Riunione Kick-off">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Inizio" name="start_at" required>
                    <input type="datetime-local" name="start_at" class="form-in @error('start_at') is-invalid @enderror"
                           value="{{ old('start_at', request()->has('start_at') ? \Carbon\Carbon::parse(request('start_at'))->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}">
                </x-form-group>
                <x-form-group label="Fine" name="end_at">
                    <input type="datetime-local" name="end_at" class="form-in @error('end_at') is-invalid @enderror"
                           value="{{ old('end_at', request()->has('end_at') ? \Carbon\Carbon::parse(request('end_at'))->format('Y-m-d\TH:i') : '') }}">
                    <div class="u-text-mono u-mt-xs">Opzionale. Se non specificata, l'evento sarà considerato "istantaneo" con chiusura immediata all'inizio.</div>
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Tipo Evento" name="type" required>
                    <select name="type" x-model="eventType" class="form-sel @error('type') is-invalid @enderror">
                        @foreach($types as $t)
                            <option value="{{ $t }}" {{ old('type', 'other') == $t ? 'selected' : '' }}>{{ (new \App\Models\CalendarEvent(['type' => $t]))->type_label }}</option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Stato" name="status" required>
                    <select name="status" class="form-sel @error('status') is-invalid @enderror">
                        @foreach($statuses as $s)
                            <option value="{{ $s }}" {{ old('status', 'scheduled') == $s ? 'selected' : '' }}>{{ (new \App\Models\CalendarEvent(['status' => $s]))->status_label }}</option>
                        @endforeach
                    </select>
                </x-form-group>
            </div>

            <div class="form-row" x-show="eventType !== 'personal'">
                <x-form-group label="Cliente (opzionale)" name="client_id">
                    <select name="client_id" id="client_sel" class="form-sel @error('client_id') is-invalid @enderror">
                        <option value="">-- Evento Interno / Nessun Cliente --</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Progetto (opzionale)" name="project_id">
                    <select name="project_id" id="project_sel" class="form-sel @error('project_id') is-invalid @enderror">
                        <option value="">-- Nessun Progetto Specifico --</option>
                    </select>
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Assegnato a" name="assigned_to" x-show="eventType !== 'personal'">
                    <select name="assigned_to" class="form-sel @error('assigned_to') is-invalid @enderror">
                        <option value="">Nessuno</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ old('assigned_to') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </x-form-group>
                <div x-show="eventType === 'personal'" class="u-flex-1">
                    <div class="form-lbl">Assegnato a</div>
                    <div class="form-in u-bg-muted u-text-muted">A me stesso (Personale)</div>
                </div>
                <x-form-group label="Luogo fisico (opzionale)" name="location">
                    <input name="location" class="form-in @error('location') is-invalid @enderror"
                           value="{{ old('location') }}" placeholder="Es. Sala Meeting, Sede o Cliente">
                </x-form-group>
            </div>

            <div class="form-row" x-data="{ provider: '{{ old('meeting_provider', 'none') }}' }">
                <x-form-group label="Provider Videochiamata" name="meeting_provider">
                    <select name="meeting_provider" x-model="provider" class="form-sel @error('meeting_provider') is-invalid @enderror">
                        <option value="none">Nessuna</option>
                        <option value="nextcloud_talk">Nextcloud Talk</option>
                        <option value="other">Altro</option>
                    </select>
                </x-form-group>
                
                <div x-show="provider !== 'none'" class="cal-provider-panel" x-cloak>
                    <x-form-group label="Link Videochiamata" name="meeting_url">
                        <input type="url" name="meeting_url" class="form-in @error('meeting_url') is-invalid @enderror"
                               value="{{ old('meeting_url') }}"
                               :placeholder="provider === 'nextcloud_talk' ? 'https://cloud.tuodominio.it/call/...' : 'https://...'">
                    </x-form-group>
                </div>
                <div x-show="provider === 'none'" class="cal-provider-panel" x-cloak></div>
            </div>

            <div class="form-row full">
                <label class="cal-checkbox-label">
                    <input type="checkbox" name="is_all_day" value="1" {{ old('is_all_day') ? 'checked' : '' }}>
                    Evento tutto il giorno
                </label>
                
                <x-form-group label="Descrizione" name="description">
                    <textarea name="description" class="form-ta @error('description') is-invalid @enderror" rows="3">{{ old('description') }}</textarea>
                </x-form-group>
            </div>

            <div class="cal-form-actions">
                <a href="{{ route('calendar-events.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva Evento</button>
            </div>
        </form>
    </x-panel>
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof initProjectSelect !== 'undefined') {
            initProjectSelect('client_sel', 'project_sel', null);
        }
    });
    </script>
    @endpush
</x-app-layout>