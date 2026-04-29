<div class="social-accounts-container" style="width:100%; margin-top:30px;">
    <x-panel title="Accessi Social" dot="var(--accent)" padded>
        <div class="social-tabs-nav" style="display:flex; gap:10px; border-bottom:1px solid var(--line); padding-bottom:10px; margin-bottom:20px;">
            @foreach($platforms as $platform)
                @php
                    $isMeta = $platform->value === 'facebook' || $platform->value === 'instagram';
                    $icon = $platform->value === 'facebook' ? 'facebook' : ($platform->value === 'instagram' ? 'instagram' : 'tiktok');
                    $isActive = $activeTab === $platform->value;
                @endphp
                <button 
                    type="button" 
                    wire:click="$set('activeTab', '{{ $platform->value }}')"
                    class="social-tab-btn"
                    style="padding:8px 16px; border-radius:6px; font-family:var(--sans); font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:8px; border:1px solid {{ $isActive ? 'var(--accent)' : 'transparent' }}; background:{{ $isActive ? 'var(--accent)15' : 'transparent' }}; color:{{ $isActive ? 'var(--accent)' : 'var(--text2)' }}; transition:all 0.2s;"
                >
                    @if($platform->value === 'facebook')
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="social-icon-sm">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    @elseif($platform->value === 'instagram')
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="social-icon-sm">
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
                        <div style="margin-bottom:15px; padding-bottom:10px; border-bottom:1px dashed var(--line);">
                            <h4 style="font-family:var(--sans); font-size:16px; color:var(--text); margin:0;">
                                Configurazione {{ $platform->label() }} <span style="font-size:12px; font-weight:normal; color:var(--text3);">{{ $titleSuffix }}</span>
                            </h4>
                        </div>
                        @if($isMeta)
                            <div class="social-account-req-notice" style="padding:10px; border-radius:6px; background:var(--orange)15; color:var(--orange); font-size:13px; margin-bottom:20px; border:1px solid var(--orange)30;">
                                <i data-lucide="alert-circle" style="width:14px; height:14px; display:inline-block; vertical-align:-2px;"></i> Richiede Meta Business Manager collegato.
                            </div>
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
                            @error('forms.'.$platform->value.'.account_url')
                                <div style="color:var(--red); font-size:11px; margin-top:4px;">{{ $message }}</div>
                            @enderror
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
                            <button 
                                type="submit" 
                                class="btn btn-p social-save-btn"
                                wire:loading.attr="disabled"
                                wire:target="save('{{ $platform->value }}')"
                            >
                                <span wire:loading.remove wire:target="save('{{ $platform->value }}')">
                                    <i data-lucide="save" class="social-icon-sm"></i> Salva {{ $platform->label() }}
                                </span>
                                <span wire:loading wire:target="save('{{ $platform->value }}')">
                                    Salvataggio...
                                </span>
                            </button>
                        </div>
                    </form>
                    </div>
                @endif
            @endforeach
        </div>
    </x-panel>
</div>
