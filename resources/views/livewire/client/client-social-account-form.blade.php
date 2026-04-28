<div x-data="{ activeTab: 'facebook' }" class="social-accounts-container">
    <div class="social-accounts-header">
        <h3 class="social-accounts-title">Accessi Social</h3>
    </div>

    <div class="social-tabs-nav">
        @foreach($platforms as $platform)
            @php
                $isMeta = $platform->value === 'facebook' || $platform->value === 'instagram';
                $titleSuffix = $isMeta ? ' (Obbligatorio)' : ' (Opzionale)';
                $icon = $platform->value === 'facebook' ? 'facebook' : ($platform->value === 'instagram' ? 'instagram' : 'tiktok');
            @endphp
            <button 
                type="button" 
                @click="activeTab = '{{ $platform->value }}'"
                :class="{'active': activeTab === '{{ $platform->value }}'}"
                class="social-tab-btn"
            >
                @if($platform->value === 'tiktok')
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="social-icon-sm">
                      <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
                    </svg>
                @else
                    <i data-lucide="{{ $icon }}" class="social-icon-sm"></i>
                @endif
                {{ $platform->label() }}
            </button>
        @endforeach
    </div>

    <div class="social-tab-contents">
        @foreach($platforms as $platform)
            @php
                $isMeta = $platform->value === 'facebook' || $platform->value === 'instagram';
                $titleSuffix = $isMeta ? ' (Obbligatorio)' : ' (Opzionale)';
                $dotColor = $platform->value === 'facebook' ? '#1877F2' : ($platform->value === 'instagram' ? '#E4405F' : '#000000');
            @endphp
            <div x-show="activeTab === '{{ $platform->value }}'" x-cloak>
                <x-panel title="{{ $platform->label() }}{{ $titleSuffix }}" dot="{{ $dotColor }}" padded class="social-account-panel">
                    @if($isMeta)
                        <div class="social-account-req-notice">Richiede Meta Business Manager collegato.</div>
                    @endif
                    <form wire:submit="save('{{ $platform->value }}')">
                        <div class="form-row">
                            <div class="form-g">
                                <label class="form-lbl">Account esiste?</label>
                                <select wire:model="forms.{{ $platform->value }}.account_exists" class="form-sel w-100">
                                    @foreach($existsOptions as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-g">
                                <label class="form-lbl">Stato Accesso Operativo</label>
                                <select wire:model="forms.{{ $platform->value }}.access_status" class="form-sel w-100">
                                    @foreach($accessStatuses as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-g">
                                <label class="form-lbl">Nome Account (es. Pagina FB)</label>
                                <input type="text" wire:model="forms.{{ $platform->value }}.account_name" class="form-in w-100">
                            </div>
                            <div class="form-g">
                                <label class="form-lbl">Username / Handle</label>
                                <input type="text" wire:model="forms.{{ $platform->value }}.username" class="form-in w-100">
                            </div>
                        </div>

                        <div class="form-g mb-3">
                            <label class="form-lbl">URL Pubblico</label>
                            <input type="url" wire:model="forms.{{ $platform->value }}.account_url" class="form-in w-100" placeholder="https://...">
                        </div>

                        <div class="form-row">
                            <div class="form-g">
                                <label class="form-lbl">Metodo Accesso</label>
                                <select wire:model="forms.{{ $platform->value }}.access_method" class="form-sel w-100">
                                    @foreach($accessMethods as $method)
                                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-g">
                                <label class="form-lbl">Dove sono le credenziali?</label>
                                <input type="text" wire:model="forms.{{ $platform->value }}.credential_location" class="form-in w-100" placeholder="es. Bitwarden, 1Password...">
                            </div>
                        </div>

                        @if($platform->value === 'facebook' || $platform->value === 'instagram')
                            <div class="form-row">
                                <div class="form-g">
                                    <label class="form-lbl">Business Manager ID</label>
                                    <input type="text" wire:model="forms.{{ $platform->value }}.business_manager_id" class="form-in w-100">
                                </div>
                                @if($platform->value === 'facebook')
                                    <div class="form-g">
                                        <label class="form-lbl">Page ID (Facebook)</label>
                                        <input type="text" wire:model="forms.{{ $platform->value }}.page_id" class="form-in w-100">
                                    </div>
                                @else
                                    <div class="form-g">
                                        <label class="form-lbl">IG Business Account ID</label>
                                        <input type="text" wire:model="forms.{{ $platform->value }}.instagram_business_account_id" class="form-in w-100">
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if($platform->value === 'tiktok')
                            <div class="form-row">
                                <div class="form-g">
                                    <label class="form-lbl">Business Center ID</label>
                                    <input type="text" wire:model="forms.{{ $platform->value }}.business_center_id" class="form-in w-100">
                                </div>
                                <div class="form-g">
                                    <label class="form-lbl">TikTok Account ID</label>
                                    <input type="text" wire:model="forms.{{ $platform->value }}.tiktok_account_id" class="form-in w-100">
                                </div>
                            </div>
                        @endif

                        <div class="form-g mb-3 social-ready-toggle">
                            <label class="form-check-lbl">
                                <input type="checkbox" wire:model="forms.{{ $platform->value }}.is_ready_to_publish" class="social-ready-checkbox">
                                <span>Questo account è operativamente PRONTO per la pubblicazione?</span>
                            </label>
                        </div>

                        <div class="form-g mb-3">
                            <label class="form-lbl">Note Operative (Manuali)</label>
                            <textarea wire:model="forms.{{ $platform->value }}.notes" class="form-ta w-100" rows="2"></textarea>
                        </div>

                        <details class="social-api-details">
                            <summary class="social-api-summary">Predisposizione API Ufficiali (Futuro)</summary>
                            <div class="social-api-content">
                                <div class="form-row">
                                    <div class="form-g">
                                        <label class="form-lbl">Provider API</label>
                                        <select wire:model="forms.{{ $platform->value }}.api_provider" class="form-sel w-100">
                                            <option value="">-- Non Selezionato --</option>
                                            @foreach($apiProviders as $provider)
                                                <option value="{{ $provider->value }}">{{ $provider->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-g">
                                        <label class="form-lbl">Status API</label>
                                        <select wire:model="forms.{{ $platform->value }}.api_status" class="form-sel w-100">
                                            @foreach($apiStatuses as $status)
                                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="form-g mt-2">
                                    <label class="form-lbl">Note API (Es. Scadenze token o warning)</label>
                                    <textarea wire:model="forms.{{ $platform->value }}.api_notes" class="form-ta w-100" rows="1"></textarea>
                                </div>
                                <div class="social-api-disclaimer">
                                    I Token crittografati sono gestiti lato server e non esposti in questa UI.
                                </div>
                            </div>
                        </details>

                        <div class="form-actions social-form-actions">
                            <div>
                                @if (session()->has('success_'.$platform->value))
                                    <div class="form-success-msg">
                                        <i data-lucide="check-circle" class="social-icon-sm"></i>
                                        {{ session('success_'.$platform->value) }}
                                    </div>
                                @endif
                            </div>
                            <button type="submit" class="btn btn-p social-save-btn">
                                <i data-lucide="save" class="social-icon-sm"></i> Salva {{ $platform->label() }}
                            </button>
                        </div>
                    </form>
                </x-panel>
            </div>
        @endforeach
    </div>
</div>
