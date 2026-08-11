<div class="social-accounts-container u-w-full u-mt-lg">
    <x-panel title="Accessi Social" dot="var(--accent)" padded>
        <div class="social-accounts-intro">
            <strong>Profili associati al cliente</strong>
            <span>Facebook e Instagram usano le connessioni Meta dell'agenzia. TikTok viene collegato direttamente per questo cliente.</span>
        </div>

        <div class="social-tabs-nav" role="tablist" aria-label="Piattaforme social del cliente">
            @foreach($platforms as $platform)
                @php
                    $isActive = $activeTab === $platform->value;
                @endphp
                <button
                    type="button"
                    wire:click="$set('activeTab', '{{ $platform->value }}')"
                    class="social-tab-btn {{ $isActive ? 'is-active' : '' }}"
                    id="social-tab-{{ $platform->value }}"
                    role="tab"
                    aria-selected="{{ $isActive ? 'true' : 'false' }}"
                    aria-controls="social-panel-{{ $platform->value }}"
                >
                    <span class="u-flex u-items-center u-gap-xs">
                        @if($platform->value === 'facebook')
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="social-icon-sm text-facebook">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        @elseif($platform->value === 'instagram')
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="social-icon-sm text-instagram">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                            </svg>
                        @elseif($platform->value === 'tiktok')
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="social-icon-sm">
                              <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
                            </svg>
                        @endif
                        {{ $platform->label() }}
                    </span>
                </button>
            @endforeach
        </div>

        <div class="social-tab-contents">
            @foreach($platforms as $platform)
                @if($activeTab === $platform->value)
                    @php
                        $isMeta = $platform->value === 'facebook' || $platform->value === 'instagram';
                        $account = $client->socialAccountFor($platform->value);
                        $isConnected = $account?->api_status === \App\Enums\Social\SocialApiStatus::Connected;
                        $isReady = $platform->value === 'tiktok'
                            ? ($account?->canPublishTikTokVideo() ?? false)
                            : ($account?->isReadyToPublish() ?? false);

                        if ($isReady) {
                            $statusLabel = $platform->value === 'tiktok' ? 'Bozze abilitate' : 'Pronto';
                            $statusClass = 'badge-success';
                        } elseif ($isConnected) {
                            $statusLabel = 'Collegato, da verificare';
                            $statusClass = 'badge-warning';
                        } else {
                            $statusLabel = $isMeta ? 'Non assegnato' : 'Non collegato';
                            $statusClass = 'badge-gray';
                        }
                    @endphp
                    <div
                        class="social-account-panel"
                        id="social-panel-{{ $platform->value }}"
                        role="tabpanel"
                        aria-labelledby="social-tab-{{ $platform->value }}"
                    >
                        <div class="social-account-heading">
                            <div>
                                <div class="u-flex u-items-center u-gap-sm">
                                    <h4 class="u-text-strong u-m-0 u-text-h4">{{ $platform->label() }}</h4>
                                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                </div>
                                <p class="social-account-copy">
                                    @if($isMeta)
                                        Assegna al cliente un profilo già disponibile nella connessione Meta dell'agenzia.
                                    @else
                                        Il collegamento TikTok è personale per questo cliente e abilita il caricamento delle bozze.
                                    @endif
                                </p>
                            </div>
                            <span class="social-requirement-label">{{ $isMeta ? 'Richiesto per pubblicare' : 'Facoltativo' }}</span>
                        </div>

                        @include('livewire.client.partials.social-manual-form', [
                            'platformValue' => $platform->value,
                            'account' => $account,
                            'isConnected' => $isConnected,
                            'isReady' => $isReady,
                        ])
                    </div>
                @endif
            @endforeach
        </div>
    </x-panel>
</div>
