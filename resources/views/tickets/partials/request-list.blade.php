@if($requests->isEmpty())
    <p class="u-text-muted">Nessun ticket trovato.</p>
@else
    <ul class="ticket-request-list">
        @foreach($requests as $requestTicket)
            <li>
                <div class="ticket-request-heading">
                    <a href="{{ route('tickets.show', $requestTicket) }}" class="u-text-accent-link">{{ $requestTicket->title }}</a>
                    <span>{{ $requestTicket->status_label }}</span>
                </div>
                <p class="u-text-meta">{{ $requestTicket->code }} · {{ $requestTicket->type_label }}</p>
                <dl class="ticket-request-details">
                    <div><dt>Cliente</dt><dd>{{ $requestTicket->client?->name }}</dd></div>
                    <div><dt>Progetto</dt><dd>{{ $requestTicket->project?->name ?? 'Non collegato' }}</dd></div>
                    <div><dt>Referente</dt><dd>{{ $requestTicket->assignee?->name ?? 'Non assegnato' }}</dd></div>
                    <div><dt>Ultimo aggiornamento</dt><dd>{{ $requestTicket->updated_at?->format('d/m/Y H:i') }}</dd></div>
                </dl>
            </li>
        @endforeach
    </ul>
@endif
