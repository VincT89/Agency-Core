<div>
    <x-panel title="Stato Onboarding Social" dot="var(--orange)" padded style="margin-bottom:20px;">
        @php
            $isMetaReady = $client->isMetaReady();
            $tiktokAccount = $client->socialAccountFor(\App\Enums\Social\SocialPlatform::Tiktok->value);
            $isTiktokReady = $tiktokAccount?->isReadyToPublish() ?? false;
        @endphp
        
        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--line);">
            <div style="display:flex; align-items:center; gap:8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="color:var(--text3);">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                <span style="font-family:var(--sans); font-size:13px; color:var(--text);">Meta (Facebook / Instagram)</span>
            </div>
            @if($isMetaReady)
                <span style="font-family:var(--mono); font-size:10px; padding:2px 6px; border-radius:4px; background:var(--green)15; color:var(--green); border:1px solid var(--green)30;">
                    META PRONTO
                </span>
            @else
                <span style="font-family:var(--mono); font-size:10px; padding:2px 6px; border-radius:4px; background:var(--orange)15; color:var(--orange); border:1px solid var(--orange)30;">
                    META INCOMPLETO
                </span>
            @endif
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--line);">
            <div style="display:flex; align-items:center; gap:8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text3);">
                    <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
                </svg>
                <span style="font-family:var(--sans); font-size:13px; color:var(--text);">TikTok (Opzionale)</span>
            </div>
            @if($isTiktokReady)
                <span style="font-family:var(--mono); font-size:10px; padding:2px 6px; border-radius:4px; background:var(--green)15; color:var(--green); border:1px solid var(--green)30;">
                    TIKTOK PRONTO
                </span>
            @else
                <span style="font-family:var(--mono); font-size:10px; padding:2px 6px; border-radius:4px; background:var(--text3)15; color:var(--text3); border:1px solid var(--text3)30;">
                    NON CONFIGURATO
                </span>
            @endif
        </div>
    </x-panel>
</div>
