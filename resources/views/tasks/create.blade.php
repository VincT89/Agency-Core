<x-app-layout title="Nuovo Task">
    <x-page-header
        eyebrow="Modulo · Operativo"
        
    >
    <x-slot:title><strong>Nuovo</strong> task</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('tasks.index') }}" class="btn btn-g">← Indietro</a>
        </x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('tasks.store') }}" method="POST">
            @csrf

            @if(isset($sourceTicket) && $sourceTicket)
                <input type="hidden" name="ticket_id" value="{{ $sourceTicket->id }}">
            @endif

            <div class="form-row full">
                <x-form-group label="Titolo task" name="title" required>
                    <input name="title" class="form-in @error('title') is-invalid @enderror"
                           value="{{ old('title', isset($sourceTicket) ? $sourceTicket->title : '') }}" placeholder="Descrizione sintetica del task...">
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Progetto" name="project_id" required>
                    <select name="project_id" class="form-sel @error('project_id') is-invalid @enderror">
                        <option value="">Seleziona progetto...</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}"
                                {{ (old('project_id', isset($sourceTicket) ? $sourceTicket->project_id : $preselectedProjectId)) == $project->id ? 'selected' : '' }}>
                                {{ $project->name }}
                                @if($project->client) — {{ $project->client->name }}@endif
                            </option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Assegnato a" name="assigned_to">
                    <select name="assigned_to" class="form-sel @error('assigned_to') is-invalid @enderror">
                        <option value="">Non assegnato</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ old('assigned_to') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Stato" name="status" required>
                    <select name="status" class="form-sel @error('status') is-invalid @enderror">
                        @foreach($statuses as $s)
                            <option value="{{ $s }}" {{ old('status', 'todo') === $s ? 'selected' : '' }}>
                                {{ (new \App\Models\Task(['status' => $s]))->status_label }}
                            </option>
                        @endforeach
                    </select>
                </x-form-group>
                <x-form-group label="Priorità" name="priority" required>
                    <select name="priority" class="form-sel @error('priority') is-invalid @enderror">
                        @foreach($priorities as $p)
                            <option value="{{ $p }}" {{ old('priority', 'medium') === $p ? 'selected' : '' }}>
                                {{ ucfirst($p) }}
                            </option>
                        @endforeach
                    </select>
                </x-form-group>
            </div>

            <div class="form-row">
                <x-form-group label="Data inizio" name="start_date">
                    <input type="date" name="start_date" class="form-in @error('start_date') is-invalid @enderror"
                           value="{{ old('start_date') }}">
                </x-form-group>
                <x-form-group label="Scadenza" name="due_date">
                    <input type="date" name="due_date" class="form-in @error('due_date') is-invalid @enderror"
                           value="{{ old('due_date') }}">
                </x-form-group>
            </div>

            <div class="form-row full">
                <x-form-group label="Descrizione" name="description">
                    <textarea name="description" class="form-ta @error('description') is-invalid @enderror"
                              rows="4" placeholder="Dettagli del task...">{{ old('description', isset($sourceTicket) ? "Generato dal Ticket #{$sourceTicket->id}\n\n{$sourceTicket->description}" : '') }}</textarea>
                </x-form-group>
            </div>

            <div class="form-row full">
                <x-form-group label="Note interne" name="notes">
                    <textarea name="notes" class="form-ta @error('notes') is-invalid @enderror"
                              rows="2" placeholder="Note private...">{{ old('notes') }}</textarea>
                </x-form-group>
            </div>

            <div class="modal-ft" style="border-top:1px solid var(--line);padding-top:16px;margin-top:16px">
                <a href="{{ route('tasks.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva Task</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
