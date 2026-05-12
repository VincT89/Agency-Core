<div class="shooting-timeline-container">
    
    {{-- Step 1: Richiesta Creata --}}
    <div class="shooting-timeline-step">
        <div class="shooting-timeline-dot green"></div>
        <div class="shooting-timeline-title text1">Richiesta Creata</div>
        <div class="shooting-timeline-desc">Da: {{ $shoot->creator->name ?? 'N/D' }} il {{ $shoot->created_at->format('d/m/Y H:i') }}</div>
    </div>
    
    {{-- Step 2: Risposta Fotografo --}}
    <div class="shooting-timeline-step">
        @php
            $hasResponded = in_array($shoot->status->value, ['waiting_client', 'client_rejected', 'photographer_rejected', 'scheduled', 'client_confirmed']);
            $isRejected = $shoot->status->value === 'photographer_rejected';
            $dotClass2 = $hasResponded ? ($isRejected ? 'red' : 'green') : 'line';
            $titleClass2 = $hasResponded ? ($isRejected ? 'red' : 'text1') : 'text3';
        @endphp
        <div class="shooting-timeline-dot {{ $dotClass2 }}">
            @if($hasResponded && $isRejected)
                <i data-lucide="x" class="shooting-timeline-icon"></i>
            @elseif($hasResponded)
                <i data-lucide="check" class="shooting-timeline-icon"></i>
            @endif
        </div>
        <div class="shooting-timeline-title {{ $titleClass2 }}">
            Risposta Fotografo
        </div>
        @if($hasResponded)
            <div class="shooting-timeline-desc">
                @if($isRejected)
                    <span class="shooting-timeline-red-text"><i data-lucide="x" class="shooting-timeline-sm-icon"></i>Il fotografo ha rifiutato.</span>
                @else
                    Il fotografo ha accettato uno slot.
                @endif
            </div>
        @else
            <div class="shooting-timeline-desc">In attesa di risposta...</div>
        @endif
    </div>
    
    {{-- Step 3: Conferma Cliente --}}
    <div class="shooting-timeline-step-last">
        @php
            // Se il fotografo ha rifiutato, questo step è annullato o non applicabile
            $isPhotographerRejected = $shoot->status->value === 'photographer_rejected';
            $hasConfirmed = in_array($shoot->status->value, ['scheduled', 'client_confirmed', 'client_rejected']);
            $clientRejected = $shoot->status->value === 'client_rejected';
            
            $dotClass3 = $isPhotographerRejected ? 'bg3' : ($hasConfirmed ? ($clientRejected ? 'red' : 'green') : 'line');
            $titleClass3 = $isPhotographerRejected ? 'text3 strike' : ($hasConfirmed ? ($clientRejected ? 'red' : 'text1') : 'text3');
        @endphp
        
        <div class="shooting-timeline-dot {{ $dotClass3 }}">
            @if($hasConfirmed && $clientRejected)
                <i data-lucide="x" class="shooting-timeline-icon"></i>
            @elseif($hasConfirmed)
                <i data-lucide="check" class="shooting-timeline-icon"></i>
            @endif
        </div>
        <div class="shooting-timeline-title {{ $titleClass3 }}">
            Conferma Cliente
        </div>
        
        @if($isPhotographerRejected)
            <div class="shooting-timeline-desc">Interrotto.</div>
        @elseif($hasConfirmed)
            <div class="shooting-timeline-desc">
                @if($clientRejected)
                    <span class="shooting-timeline-red-text"><i data-lucide="x" class="shooting-timeline-sm-icon"></i>Il cliente ha rifiutato lo slot.</span>
                @else
                    Il cliente ha confermato lo shooting.
                @endif
            </div>
        @else
            <div class="shooting-timeline-desc">In attesa del cliente...</div>
        @endif
    </div>

</div>
