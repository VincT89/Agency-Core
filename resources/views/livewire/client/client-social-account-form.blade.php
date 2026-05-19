<div class="social-accounts-container u-w-full u-mt-lg">
    <x-panel title="Accessi Social" dot="var(--accent)" padded>
        <div class="social-tabs-nav u-flex u-gap-sm u-pb-md u-border-b u-mb-md">
            @foreach($platforms as $platform)
                @php
                    $isMeta = $platform->value === 'facebook' || $platform->value === 'instagram';
                    $icon = $platform->value === 'facebook' ? 'facebook' : ($platform->value === 'instagram' ? 'instagram' : 'tiktok');
                    $isActive = $activeTab === $platform->value;
                @endphp
                <button 
                    type="button" 
                    wire:click="$set('activeTab', '{{ $platform->value }}')"
                    class="social-tab-btn {{ $isActive ? 'is-active' : '' }}"
                >
                    <div class="u-flex u-items-center u-gap-xs">
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
                    </div>
                </button>
            @endforeach
        </div>

        <div class="social-tab-contents">
            @foreach($platforms as $platform)
                @if($activeTab === $platform->value)
                    @php
                        $isMeta = $platform->value === 'facebook' || $platform->value === 'instagram';
                        $titleSuffix = $isMeta ? ' (Obbligatorio)' : ' (Opzionale)';
                    @endphp
                    <div class="social-account-panel">
                        <div class="u-pb-md u-mb-md u-border-b-dashed">
                            <h4 class="u-text-strong u-m-0 u-text-h4">
                                Configurazione {{ $platform->label() }} <span class="u-text-sm u-text-normal u-text-muted">{{ $titleSuffix }}</span>
                            </h4>
                        </div>
                        
                        @if($isMeta)
                            <div class="social-account-req-notice u-alert-warning u-mb-md">
                                <i data-lucide="alert-circle" class="u-icon-sm"></i> Richiede Meta Business Manager collegato.
                            </div>
                        @endif
                        
                        @include('livewire.client.partials.social-manual-form', ['platformValue' => $platform->value])
                    </div>
                @endif
            @endforeach
        </div>
    </x-panel>
</div>
