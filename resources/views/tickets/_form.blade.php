@php
    $commercial = auth()->user()->isCommercial();
    $selectedType = old('type', $ticket?->type ?? 'request');
@endphp
<form action="{{ $ticket ? route('tickets.update', $ticket) : route('tickets.store') }}" method="POST" data-ticket-form>
    @csrf
    @if($ticket) @method('PATCH') @endif

    @if($commercial)
        <p class="ticket-create-note">Descrivi la richiesta del cliente. L’amministratore la valuterà e assegnerà il lavoro al reparto competente.</p>
    @endif

    <div class="form-row full">
        <x-form-group label="Oggetto" name="title" required>
            <input name="title" class="form-in" value="{{ old('title', $ticket?->title) }}" maxlength="255" required>
        </x-form-group>
    </div>
    <div class="form-row">
        <x-form-group label="Cliente" name="client_id" required>
            <select name="client_id" id="client_sel" class="form-sel" data-client-select data-project-select="project_sel"
                data-project-url="{{ url('/api/ticket-clients/{client}/projects') }}"
                data-current-project="{{ old('project_id', $ticket?->project_id) }}"
                data-empty-project-help="Il cliente non ha progetti attivi. Puoi inviare una richiesta di preventivo senza progetto." required>
                <option value="">Seleziona cliente</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" @selected(old('client_id', $ticket?->client_id) == $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
        </x-form-group>
        <x-form-group label="Progetto" name="project_id">
            <select name="project_id" id="project_sel" class="form-sel" aria-describedby="project_sel_help ticket-project-rule" @required($selectedType !== 'quote')>
                <option value="">Seleziona progetto</option>
                @if($ticket?->project)
                    <option value="{{ $ticket->project_id }}" selected>{{ $ticket->project->name }}</option>
                @endif
            </select>
            <p id="ticket-project-rule" class="u-text-meta">Facoltativo per le richieste di preventivo; obbligatorio per gli interventi.</p>
            <p id="project_sel_help" class="u-text-meta" role="status"></p>
        </x-form-group>
    </div>
    <div class="form-row">
        <x-form-group label="Motivo della richiesta" name="type" required>
            <select name="type" class="form-sel" data-ticket-type required>
                @foreach($types as $type)
                    <option value="{{ $type }}" @selected($selectedType === $type)>{{ (new \App\Models\Ticket(['type' => $type]))->type_label }}</option>
                @endforeach
            </select>
        </x-form-group>
        <x-form-group label="Area della richiesta" name="requested_department">
            <select name="requested_department" class="form-sel">
                <option value="">Da valutare con l’amministratore</option>
                @foreach(\App\Models\Ticket::DEPARTMENTS as $value => $label)
                    <option value="{{ $value }}" @selected(old('requested_department', $ticket?->requested_department) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-form-group>
    </div>
    <div class="form-row">
        <x-form-group label="Priorità" name="priority" required>
            <select name="priority" class="form-sel" required>
                @foreach($priorities as $priority)
                    <option value="{{ $priority }}" @selected(old('priority', $ticket?->priority ?? 'medium') === $priority)>{{ (new \App\Models\Ticket(['priority' => $priority]))->priority_label }}</option>
                @endforeach
            </select>
        </x-form-group>
        @if(!$commercial)
            <x-form-group label="Stato" name="status" required>
                <select name="status" class="form-sel" required>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $ticket?->status ?? 'open') === $status)>{{ (new \App\Models\Ticket(['status' => $status]))->status_label }}</option>
                    @endforeach
                </select>
            </x-form-group>
        @endif
    </div>
    @if(!$commercial)
        @include('shared.department-assignee', ['selectedAssignee' => old('assigned_to', $ticket?->assigned_to), 'assignmentContext' => 'ticket'])
        <p class="u-text-meta">Qui scegli il referente del ticket. Per affidare attività a Marketing e agli altri reparti, usa “Assegna lavoro a un reparto” dal dettaglio del ticket.</p>
    @endif
    <div class="form-row full">
        <x-form-group label="Descrizione della richiesta" name="description">
            <textarea name="description" class="form-ta" rows="5">{{ old('description', $ticket?->description) }}</textarea>
        </x-form-group>
    </div>
    <div class="modal-ft u-section-sep ticket-form-actions">
        <a href="{{ $ticket ? route('tickets.show', $ticket) : route('tickets.index') }}" class="btn btn-g">Annulla</a>
        <button type="submit" class="btn btn-p">{{ $ticket ? 'Salva ticket' : ($commercial ? 'Invia ticket' : 'Crea ticket') }}</button>
    </div>
</form>
