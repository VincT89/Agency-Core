<div style="border-left:2px solid var(--line); margin-left:12px; padding-left:24px; position:relative;">
    
    <!-- Step 1: Richiesta Creata -->
    <div style="position:relative; margin-bottom:24px;">
        <div style="position:absolute; left:-33px; top:0; width:16px; height:16px; border-radius:50%; background:var(--green); border:2px solid var(--bg1);"></div>
        <div style="font-weight:600; font-size:14px; color:var(--text1);">Richiesta Creata</div>
        <div style="font-size:13px; color:var(--text3);">Da: {{ $shoot->creator->name ?? 'N/D' }} il {{ $shoot->created_at->format('d/m/Y H:i') }}</div>
    </div>
    
    <!-- Step 2: Risposta Fotografo -->
    <div style="position:relative; margin-bottom:24px;">
        @php
            $hasResponded = in_array($shoot->status->value, ['waiting_client', 'client_rejected', 'photographer_rejected', 'scheduled', 'client_confirmed']);
            $isRejected = $shoot->status->value === 'photographer_rejected';
        @endphp
        <div style="position:absolute; left:-33px; top:0; width:16px; height:16px; border-radius:50%; background:{{ $hasResponded ? ($isRejected ? 'var(--red)' : 'var(--green)') : 'var(--line)' }}; border:2px solid var(--bg1); display:flex; align-items:center; justify-content:center;">
            @if($hasResponded && $isRejected)
                <i data-lucide="x" style="color:var(--bg1); width:10px; height:10px; stroke-width:3px;"></i>
            @elseif($hasResponded)
                <i data-lucide="check" style="color:var(--bg1); width:10px; height:10px; stroke-width:3px;"></i>
            @endif
        </div>
        <div style="font-weight:600; font-size:14px; color:{{ $hasResponded ? ($isRejected ? 'var(--red)' : 'var(--text1)') : 'var(--text3)' }};">
            Risposta Fotografo
        </div>
        @if($hasResponded)
            <div style="font-size:13px; color:var(--text3);">
                @if($isRejected)
                    <span style="color:var(--red); font-weight:600;"><i data-lucide="x" style="width:12px; height:12px; display:inline-block; margin-right:4px; vertical-align:middle;"></i>Il fotografo ha rifiutato.</span>
                @else
                    Il fotografo ha accettato uno slot.
                @endif
            </div>
        @else
            <div style="font-size:13px; color:var(--text3);">In attesa di risposta...</div>
        @endif
    </div>
    
    <!-- Step 3: Conferma Cliente -->
    <div style="position:relative;">
        @php
            // Se il fotografo ha rifiutato, questo step è annullato o non applicabile
            $isPhotographerRejected = $shoot->status->value === 'photographer_rejected';
            $hasConfirmed = in_array($shoot->status->value, ['scheduled', 'client_confirmed', 'client_rejected']);
            $clientRejected = $shoot->status->value === 'client_rejected';
        @endphp
        
        <div style="position:absolute; left:-33px; top:0; width:16px; height:16px; border-radius:50%; background:{{ $isPhotographerRejected ? 'var(--bg3)' : ($hasConfirmed ? ($clientRejected ? 'var(--red)' : 'var(--green)') : 'var(--line)') }}; border:2px solid var(--bg1); display:flex; align-items:center; justify-content:center;">
            @if($hasConfirmed && $clientRejected)
                <i data-lucide="x" style="color:var(--bg1); width:10px; height:10px; stroke-width:3px;"></i>
            @elseif($hasConfirmed)
                <i data-lucide="check" style="color:var(--bg1); width:10px; height:10px; stroke-width:3px;"></i>
            @endif
        </div>
        <div style="font-weight:600; font-size:14px; color:{{ $isPhotographerRejected ? 'var(--text3)' : ($hasConfirmed ? ($clientRejected ? 'var(--red)' : 'var(--text1)') : 'var(--text3)') }}; {{ $isPhotographerRejected ? 'text-decoration:line-through;' : '' }}">
            Conferma Cliente
        </div>
        
        @if($isPhotographerRejected)
            <div style="font-size:13px; color:var(--text3);">Interrotto.</div>
        @elseif($hasConfirmed)
            <div style="font-size:13px; color:var(--text3);">
                @if($clientRejected)
                    <span style="color:var(--red); font-weight:600;"><i data-lucide="x" style="width:12px; height:12px; display:inline-block; margin-right:4px; vertical-align:middle;"></i>Il cliente ha rifiutato lo slot.</span>
                @else
                    Il cliente ha confermato lo shooting.
                @endif
            </div>
        @else
            <div style="font-size:13px; color:var(--text3);">In attesa del cliente...</div>
        @endif
    </div>

</div>
