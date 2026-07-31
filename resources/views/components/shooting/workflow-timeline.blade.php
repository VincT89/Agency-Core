@php
    $status = $shoot->status;
    $photographerAccepted = in_array($status, [
        \App\Enums\Shooting\ShootStatus::WaitingClient,
        \App\Enums\Shooting\ShootStatus::ClientRejected,
        \App\Enums\Shooting\ShootStatus::Scheduled,
    ], true);
    $photographerRejected = $status === \App\Enums\Shooting\ShootStatus::PhotographerRejected;
    $clientInformed = (bool) $shoot->client_notified_at;
    $clientRejected = $status === \App\Enums\Shooting\ShootStatus::ClientRejected;
    $clientAccepted = $status === \App\Enums\Shooting\ShootStatus::Scheduled;
@endphp

<div class="shooting-timeline-container">
    <div class="shooting-timeline-step">
        <div class="shooting-timeline-dot green">
            <i data-lucide="check" class="shooting-timeline-icon"></i>
        </div>
        <div class="shooting-timeline-title text1">Richiesta creata</div>
        <div class="shooting-timeline-desc">
            {{ $shoot->creator->name ?? 'Team interno' }}
            · {{ $shoot->created_at->format('d/m/Y H:i') }}
        </div>
    </div>

    <div class="shooting-timeline-step">
        <div class="shooting-timeline-dot {{ $photographerRejected ? 'red' : ($photographerAccepted ? 'green' : 'line') }}">
            @if($photographerRejected)
                <i data-lucide="x" class="shooting-timeline-icon"></i>
            @elseif($photographerAccepted)
                <i data-lucide="check" class="shooting-timeline-icon"></i>
            @endif
        </div>
        <div class="shooting-timeline-title {{ $photographerRejected ? 'red' : ($photographerAccepted ? 'text1' : 'text3') }}">
            Risposta fotografo
        </div>
        <div class="shooting-timeline-desc">
            @if($photographerRejected)
                Date rifiutate: serve una nuova proposta.
            @elseif($photographerAccepted)
                Disponibilità confermata.
            @else
                In attesa del fotografo.
            @endif
        </div>
    </div>

    <div class="shooting-timeline-step">
        <div class="shooting-timeline-dot {{ $clientInformed ? 'green' : 'line' }}">
            @if($clientInformed)
                <i data-lucide="check" class="shooting-timeline-icon"></i>
            @endif
        </div>
        <div class="shooting-timeline-title {{ $clientInformed ? 'text1' : 'text3' }}">
            Cliente informato
        </div>
        <div class="shooting-timeline-desc">
            @if($clientInformed)
                {{ \App\Enums\Shooting\ShootClientContactChannel::tryFrom($shoot->client_confirmation_channel)?->label() ?? 'Canale registrato' }}
                · {{ $shoot->client_notified_at->format('d/m/Y H:i') }}
            @elseif($photographerAccepted)
                Il marketing deve contattare il cliente.
            @else
                Disponibile dopo la risposta del fotografo.
            @endif
        </div>
    </div>

    <div class="shooting-timeline-step-last">
        <div class="shooting-timeline-dot {{ $clientRejected ? 'red' : ($clientAccepted ? 'green' : 'line') }}">
            @if($clientRejected)
                <i data-lucide="x" class="shooting-timeline-icon"></i>
            @elseif($clientAccepted)
                <i data-lucide="check" class="shooting-timeline-icon"></i>
            @endif
        </div>
        <div class="shooting-timeline-title {{ $clientRejected ? 'red' : ($clientAccepted ? 'text1' : 'text3') }}">
            Risposta cliente
        </div>
        <div class="shooting-timeline-desc">
            @if($clientRejected)
                Data rifiutata: prepara una nuova proposta.
            @elseif($clientAccepted)
                Confermato e pianificato.
            @elseif($clientInformed)
                In attesa della risposta.
            @else
                Non ancora disponibile.
            @endif
        </div>
    </div>
</div>
