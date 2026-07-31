<form wire:submit="save('{{ $platformValue }}')">
    <div class="form-row">
        <div class="form-g">
            <label class="form-lbl">Modalità di collegamento</label>
            <select wire:model.live="forms.{{ $platformValue }}.connection_strategy" class="form-sel w-100">
                @if($platformValue === 'tiktok')
                    <option value="platform_oauth">Collegamento guidato a TikTok</option>
                @else
                    <option value="agency_oauth">Collegamento gestito dall'agenzia</option>
                    <option value="manual_token_config">Configurazione manuale</option>
                @endif
            </select>
        </div>
        <div class="form-g">
            <label class="form-lbl">Profilo già esistente?</label>
            <select wire:model="forms.{{ $platformValue }}.account_exists" class="form-sel w-100">

                <!-- Usa l'array $existsOptions globale dal componente -->
                @foreach($existsOptions as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-g">
            <label class="form-lbl">
                @if($platformValue === 'tiktok')
                    Nome Profilo TikTok
                @else
                    Nome pagina o profilo
                @endif
            </label>
            <input type="text" wire:model="forms.{{ $platformValue }}.account_name" class="form-in w-100">
        </div>
        <div class="form-g">
            <label class="form-lbl">Nome utente</label>
            <input type="text" wire:model="forms.{{ $platformValue }}.username" class="form-in w-100">
        </div>
    </div>

    <div class="form-g mb-3">
        <label class="form-lbl">Link pubblico</label>
        <input type="url" wire:model="forms.{{ $platformValue }}.account_url" class="form-in w-100" placeholder="https://...">
        @error('forms.'.$platformValue.'.account_url')
            <div class="u-text-xs u-text-red u-mt-xs">{{ $message }}</div>
        @enderror
    </div>

    @if($forms[$platformValue]['connection_strategy'] === 'manual_token_config')
        <div class="form-row">
            <div class="form-g">
                <label class="form-lbl">Stato del collegamento</label>
                <select wire:model="forms.{{ $platformValue }}.access_status" class="form-sel w-100">
                    @foreach($accessStatuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-g">
                <label class="form-lbl">Modalità di accesso</label>
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
                <span>Account pronto per la pubblicazione</span>
            </label>
        </div>
    @elseif($forms[$platformValue]['connection_strategy'] === 'platform_oauth')
        <div class="social-api-panel u-mt-lg u-pt-md u-border-t-dashed">
            <h5 class="u-text-strong u-mb-md u-mt-0 u-flex u-align-center u-gap-xs u-text-lg">
                <i data-lucide="link" class="u-text-blue u-icon-md"></i> Collegamento a {{ ucfirst($platformValue) }}
            </h5>
            
            @php
                $account = $client->socialAccountFor($platformValue);
                $isConnected = $account && $account->api_status === \App\Enums\Social\SocialApiStatus::Connected;
            @endphp

            @if($isConnected)
                <div class="u-alert-success u-mb-md">
                    <div class="u-flex u-align-start u-gap-sm">
                        <i data-lucide="check-circle" class="u-icon-md"></i>
                        <div>
                            <div class="u-mb-xs"><strong>Account collegato</strong></div>
                            <div class="u-text-muted">Il collegamento è stato salvato in sicurezza.</div>
                        </div>
                    </div>
                    @if($platformValue === 'tiktok' && !$account->canPublishTikTokVideo())
                        <div class="u-mt-sm u-text-sm" style="margin-left: 2.25rem;">
                            <em>La pubblicazione sarà disponibile dopo il completamento delle verifiche richieste da TikTok.</em>
                        </div>
                    @endif
                </div>
                <div class="u-flex u-gap-sm">
                    <button type="button" wire:click="disconnectOauth('{{ $platformValue }}')" class="btn btn-error" wire:confirm="Scollegare definitivamente questo account?">
                        Scollega Account
                    </button>
                </div>
            @else
                <div class="u-alert-warning u-mb-md">
                    <div class="u-flex u-align-start u-gap-sm">
                        <i data-lucide="alert-triangle" class="u-icon-md"></i>
                        <div>
                            <div class="u-mb-xs"><strong>Account non collegato</strong></div>
                            <div class="u-text-muted">Collega il profilo per abilitare la pubblicazione.</div>
                        </div>
                    </div>
                </div>
                
                @if($account)
                    <button type="button" wire:click="startTikTokOauth('{{ $platformValue }}')" class="btn btn-p u-inline-flex u-align-center u-gap-xs u-mb-md">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-top: -2px;">
                            <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
                        </svg>
                        Collega {{ ucfirst($platformValue) }}
                    </button>
                @else
                    <button type="button" class="btn btn-p u-inline-flex u-align-center u-gap-xs u-mb-md" disabled title="Salva prima il profilo TikTok.">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-top: -2px;">
                            <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
                        </svg>
                        Collega {{ ucfirst($platformValue) }}
                    </button>
                    <div class="u-text-sm u-text-muted u-mt-xs">
                        Salva prima il profilo TikTok, poi avvia il collegamento.
                    </div>
                @endif
            @endif
        </div>
    @elseif($forms[$platformValue]['connection_strategy'] === 'agency_oauth')
        <div class="u-alert-info u-mb-lg u-mt-lg">
            <div class="u-flex u-align-start u-gap-sm">
                <i data-lucide="info" class="u-icon-md u-text-blue"></i>
                <div>
                    <div class="u-mb-xs"><strong class="u-text-blue">Collegamento gestito dall'agenzia</strong></div>
                    <div class="u-text-muted">
                        Il collegamento a questa piattaforma è gestito automaticamente dall'agenzia.<br>
                        Non è necessario inserire credenziali. Lo stato della pubblicazione verrà aggiornato in automatico.
                    </div>
                </div>
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
            <i data-lucide="shield-check" style="color: #16a34a; width: 24px; height: 24px;"></i> Profilo social dell'agenzia
        </h5>
        
        <div class="form-g" style="margin-bottom: 2rem;">
            <label class="form-lbl" style="display: block; margin-bottom: 0.5rem;">Seleziona il profilo da assegnare</label>
            
            @if(empty($availableAssets))
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mt-2 rounded-md">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mt-0.5">
                            <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700 font-medium">
                                Nessun profilo Meta disponibile per questa piattaforma.
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
                    <option value="">-- Seleziona un profilo disponibile --</option>
                    @foreach($availableAssets as $asset)
                        @php
                            $statusLabel = [];
                            if(!$asset->is_active) $statusLabel[] = 'Disattivato';
                            if($asset->revoked_at) $statusLabel[] = 'Revocato';
                            if($asset->connection && $asset->connection->requires_reauth) $statusLabel[] = 'Collegamento da rinnovare';
                            $statusStr = !empty($statusLabel) ? ' [' . implode(', ', $statusLabel) . ']' : '';
                        @endphp
                        <option value="{{ $asset->id }}">
                            {{ $asset->name }} {{ $asset->username ? '(@' . $asset->username . ')' : '' }}
                            (Collegamento: {{ $asset->connection->provider_user_name ?? 'Non disponibile' }}){{ $statusStr }}
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
                @if(!$selectedAsset->is_active || $selectedAsset->revoked_at || ($selectedAsset->connection && $selectedAsset->connection->requires_reauth) || !$selectedAsset->is_assignable)
                    <div class="u-alert-error u-mb-sm" style="padding: 1rem; border-radius: 6px; background-color: #fef2f2; border: 1px solid #f87171; color: #b91c1c;">
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <i data-lucide="alert-triangle" class="u-icon-sm"></i>
                            <strong>Collegamento non disponibile.</strong> Seleziona un altro profilo o rinnova il collegamento.
                        </div>
                    </div>
                @endif
                <div class="bg-gray-50 rounded-lg p-4 mb-4 border border-gray-200">
                    <h6 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wider">Profilo selezionato</h6>
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
                                {{ $selectedAsset->username ? '@' . $selectedAsset->username : 'Nome utente non disponibile' }}
                            </div>
                            <div class="flex gap-2 mt-2">
                                <span class="px-2 py-1 text-xs rounded-md bg-blue-100 text-blue-800 border border-blue-200">
                                    {{ $selectedAsset->asset_type->label() }}
                                </span>
                                <span class="px-2 py-1 text-xs rounded-md bg-green-100 text-green-800 border border-green-200">
                                    {{ $selectedAsset->publishing_status?->label() ?? 'Pronto' }}
                                </span>
                                @if(!$selectedAsset->is_active)
                                    <span class="px-2 py-1 text-xs rounded-md bg-red-100 text-red-800 border border-red-200">
                                        Disattivato
                                    </span>
                                @endif
                                @if($selectedAsset->connection && $selectedAsset->connection->requires_reauth)
                                    <span class="px-2 py-1 text-xs rounded-md bg-red-100 text-red-800 border border-red-200" title="La connessione richiede riautenticazione">
                                        Collegamento da rinnovare
                                    </span>
                                @endif
                                @if(!$selectedAsset->is_assignable)
                                    <span class="px-2 py-1 text-xs rounded-md bg-orange-100 text-orange-800 border border-orange-200">
                                        Non disponibile
                                    </span>
                                @endif
                                @if($selectedAsset->revoked_at)
                                    <span class="px-2 py-1 text-xs rounded-md bg-red-100 text-red-800 border border-red-200">
                                        Revocato
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif
        
        @if($forms[$platformValue]['api_status'] === 'connected')
            <div class="u-flex u-gap-sm u-mt-md">
                <button type="button" wire:click="disconnect('{{ $platformValue }}')" class="btn btn-error" wire:loading.attr="disabled" onclick="return confirm('Vuoi rimuovere il profilo social assegnato?')">
                    Rimuovi
                </button>
            </div>
        @endif
        
        <div class="form-g" style="margin-top: 2rem; margin-bottom: 1rem;">
            <label class="form-lbl" style="display: block; margin-bottom: 0.5rem;">Note sul collegamento</label>
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
