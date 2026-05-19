<form wire:submit="save('{{ $platformValue }}')">
    <div class="form-row">
        <div class="form-g">
            <label class="form-lbl">Strategia di Connessione</label>
            <select wire:model.live="forms.{{ $platformValue }}.connection_strategy" class="form-sel w-100">
                <option value="agency_oauth">Gestione Agenzia (OAuth Centralizzato)</option>
                <option value="manual_token_config">Configurazione Manuale (Token/App)</option>
            </select>
        </div>
        <div class="form-g">
            <label class="form-lbl">Account esiste?</label>
            <select wire:model="forms.{{ $platformValue }}.account_exists" class="form-sel w-100">
                @foreach(\App\Enums\Social\SocialPlatform::cases() as $p)
                    @php if($p->value !== $platformValue) continue; @endphp
                @endforeach
                <!-- Usa l'array $existsOptions globale dal componente -->
                @foreach($existsOptions as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-g">
            <label class="form-lbl">Nome Account (es. Pagina FB)</label>
            <input type="text" wire:model="forms.{{ $platformValue }}.account_name" class="form-in w-100">
        </div>
        <div class="form-g">
            <label class="form-lbl">Username / Handle</label>
            <input type="text" wire:model="forms.{{ $platformValue }}.username" class="form-in w-100">
        </div>
    </div>

    <div class="form-g mb-3">
        <label class="form-lbl">URL Pubblico</label>
        <input type="url" wire:model="forms.{{ $platformValue }}.account_url" class="form-in w-100" placeholder="https://...">
        @error('forms.'.$platformValue.'.account_url')
            <div class="u-text-xs u-text-red u-mt-xs">{{ $message }}</div>
        @enderror
    </div>

    @if($forms[$platformValue]['connection_strategy'] === 'manual_token_config')
        <div class="form-row">
            <div class="form-g">
                <label class="form-lbl">Stato Accesso Operativo</label>
                <select wire:model="forms.{{ $platformValue }}.access_status" class="form-sel w-100">
                    @foreach($accessStatuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-g">
                <label class="form-lbl">Metodo Accesso</label>
                <select wire:model="forms.{{ $platformValue }}.access_method" class="form-sel w-100">
                    @foreach($accessMethods as $method)
                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-g">
                <label class="form-lbl">Dove sono le credenziali?</label>
                <input type="text" wire:model="forms.{{ $platformValue }}.credential_location" class="form-in w-100" placeholder="es. Bitwarden, 1Password...">
            </div>
            @if($platformValue === 'facebook' || $platformValue === 'instagram')
                <div class="form-g">
                    <label class="form-lbl">Business Manager ID</label>
                    <input type="text" wire:model="forms.{{ $platformValue }}.business_manager_id" class="form-in w-100">
                </div>
            @endif
            @if($platformValue === 'tiktok')
                <div class="form-g">
                    <label class="form-lbl">Business Center ID</label>
                    <input type="text" wire:model="forms.{{ $platformValue }}.business_center_id" class="form-in w-100">
                </div>
            @endif
        </div>

        @if($platformValue === 'tiktok')
            <div class="form-row">
                <div class="form-g">
                    <label class="form-lbl">TikTok Account ID</label>
                    <input type="text" wire:model="forms.{{ $platformValue }}.tiktok_account_id" class="form-in w-100">
                </div>
            </div>
        @endif
        
        <div class="form-g mb-3 social-ready-toggle">
            <label class="form-check-lbl">
                <input type="checkbox" wire:model="forms.{{ $platformValue }}.is_ready_to_publish" class="social-ready-checkbox">
                <span>Questo account è operativamente PRONTO per la pubblicazione?</span>
            </label>
        </div>
    @else
        <div class="u-alert-info u-text-sm" style="padding: 1.5rem; margin-top: 2.5rem; margin-bottom: 2.5rem; border-radius: 8px; border-left: 4px solid #3b82f6; background-color: #eff6ff;">
            <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.75rem;">
                <i data-lucide="info" class="u-icon-sm" style="color: #2563eb;"></i>
                <strong style="color: #1e40af; font-size: 1.1em;">Gestione Centralizzata Attiva</strong>
            </div>
            <div style="color: #1e3a8a; line-height: 1.6; margin-left: 2rem;">
                Poiché stai utilizzando la modalità <strong>Gestione Agenzia (OAuth)</strong>, lo stato di pubblicazione (<em>is_ready_to_publish</em>) e i parametri di accesso vengono elaborati in modo completamente automatico sincronizzando l'Asset.<br>
                Usa il pannello sottostante per selezionare l'Asset corretto.
            </div>
        </div>
    @endif

    <div class="form-g" style="margin-bottom: 3rem;">
        <label class="form-lbl">Note Interne Account (Opzionale)</label>
        <textarea wire:model="forms.{{ $platformValue }}.notes" class="form-ta w-100" rows="2" placeholder="Appunti liberi sull'account..."></textarea>
    </div>

    @if($forms[$platformValue]['connection_strategy'] === 'agency_oauth')
    <div class="social-api-panel u-section-sep" style="margin-top: 3.5rem; padding-top: 2.5rem; border-top: 2px dashed #e5e7eb;">
        <h5 class="u-text-strong" style="margin-bottom: 1.5rem; margin-top: 0; display: flex; align-items: center; gap: 0.75rem; font-size: 1.15rem;">
            <i data-lucide="shield-check" style="color: #16a34a; width: 24px; height: 24px;"></i> Assegnazione Asset Agenzia
        </h5>
        
        <div class="form-g" style="margin-bottom: 2rem;">
            <label class="form-lbl" style="display: block; margin-bottom: 0.5rem;">Seleziona Asset da Assegnare</label>
            
            @if(empty($availableAssets))
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mt-2 rounded-md">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mt-0.5">
                            <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700 font-medium">
                                Nessun asset Meta sincronizzato o disponibile per questa piattaforma.
                            </p>
                            @can('manage_social_connections')
                                <p class="mt-2 text-sm">
                                    <a href="{{ route('admin.social.connections.index') }}" class="font-medium text-yellow-700 underline hover:text-yellow-600">
                                        Vai a Connessioni Social per collegare Meta e sincronizzare le pagine
                                    </a>
                                </p>
                            @endcan
                        </div>
                    </div>
                </div>
            @else
                <select wire:model.live="forms.{{ $platformValue }}.agency_social_asset_id" 
                        wire:change="validateAssetAssignment('{{ $platformValue }}', $event.target.value)"
                        class="form-sel w-100">
                    <option value="">-- Seleziona un asset sincronizzato --</option>
                    @foreach($availableAssets as $asset)
                        <option value="{{ $asset->id }}">
                            {{ $asset->name }} {{ $asset->username ? '(@' . $asset->username . ')' : '' }}
                            (Connessione: {{ $asset->connection->provider_user_name ?? 'N/A' }})
                        </option>
                    @endforeach
                </select>
            @endif
        </div>

        @if(!empty($forms[$platformValue]['agency_social_asset_id']))
            @php
                $selectedAsset = collect($availableAssets ?? [])->firstWhere('id', $forms[$platformValue]['agency_social_asset_id']);
            @endphp
            @if($selectedAsset)
                <div class="bg-gray-50 rounded-lg p-4 mb-4 border border-gray-200">
                    <h6 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wider">Preview Asset Selezionato</h6>
                    <div class="flex items-start gap-4">
                        @if(isset($selectedAsset->raw_payload['picture']['data']['url']) || isset($selectedAsset->raw_payload['profile_picture_url']))
                            <img src="{{ $selectedAsset->raw_payload['profile_picture_url'] ?? $selectedAsset->raw_payload['picture']['data']['url'] }}" 
                                 class="w-16 h-16 rounded-full border border-gray-300 shadow-sm object-cover">
                        @else
                            <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-gray-400">
                                <i class="fas fa-image text-2xl"></i>
                            </div>
                        @endif
                        <div>
                            <div class="text-lg font-bold text-gray-900">{{ $selectedAsset->name }}</div>
                            <div class="text-gray-500 text-sm mb-1">
                                {{ $selectedAsset->username ? '@' . $selectedAsset->username : 'ID: ' . $selectedAsset->provider_asset_id }}
                            </div>
                            <div class="flex gap-2 mt-2">
                                <span class="px-2 py-1 text-xs rounded-md bg-blue-100 text-blue-800 border border-blue-200">
                                    {{ $selectedAsset->asset_type->label() }}
                                </span>
                                <span class="px-2 py-1 text-xs rounded-md bg-green-100 text-green-800 border border-green-200">
                                    {{ $selectedAsset->publishing_status?->label() ?? 'Pronto' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif
        
        @if($forms[$platformValue]['api_status'] === 'connected')
            <div class="u-flex u-gap-sm u-mt-md">
                <button type="button" wire:click="testConnection('{{ $platformValue }}')" class="btn btn-s" wire:loading.attr="disabled">
                    Test Assegnazione
                </button>
                <button type="button" wire:click="disconnect('{{ $platformValue }}')" class="btn btn-error" wire:loading.attr="disabled" onclick="return confirm('Sicuro di voler rimuovere l\'assegnazione di questo asset?')">
                    Rimuovi
                </button>
            </div>
        @endif
        
        <div class="form-g" style="margin-top: 2rem; margin-bottom: 1rem;">
            <label class="form-lbl" style="display: block; margin-bottom: 0.5rem;">Note Assegnazione (es. Vincoli o richieste specifiche del brand)</label>
            <textarea wire:model="forms.{{ $platformValue }}.api_notes" class="form-ta w-100" rows="2" style="margin-top: 0.25rem;"></textarea>
        </div>
    </div>
    @endif

    <div class="form-actions social-form-actions u-mt-lg">
        <div>
            @if (session()->has('success_'.$platformValue))
                <div class="form-success-msg">
                    <i data-lucide="check-circle" class="social-icon-sm"></i>
                    {{ session('success_'.$platformValue) }}
                </div>
            @endif
        </div>
        <button 
            type="submit" 
            class="btn btn-p social-save-btn u-flex u-align-center u-gap-sm"
            wire:loading.attr="disabled"
            wire:target="save('{{ $platformValue }}')"
        >
            <span class="u-flex u-align-center u-gap-xs" wire:loading.remove wire:target="save('{{ $platformValue }}')">
                <i data-lucide="save" class="u-icon-sm"></i> Salva
            </span>
            <span class="u-flex u-align-center u-gap-xs" wire:loading wire:target="save('{{ $platformValue }}')">
                <i data-lucide="loader" class="u-icon-sm icon-spin"></i> Salvataggio...
            </span>
        </button>
    </div>
</form>
