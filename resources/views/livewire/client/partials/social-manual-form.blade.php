@php
    $isTikTok = $platformValue === 'tiktok';
    $isMeta = $platformValue === 'facebook' || $platformValue === 'instagram';
    $strategy = $forms[$platformValue]['connection_strategy'];
    $isManual = $strategy === 'manual_token_config';
    $isAgencyOauth = $strategy === 'agency_oauth';
    $isPlatformOauth = $strategy === 'platform_oauth';
    $account = $account ?? $client->socialAccountFor($platformValue);
    $isConnected = $isConnected ?? ($account?->api_status === \App\Enums\Social\SocialApiStatus::Connected);
    $isReady = $isReady ?? ($isTikTok
        ? ($account?->canPublishTikTokVideo() ?? false)
        : ($account?->isReadyToPublish() ?? false));
    $assets = collect($availableAssets ?? []);
    $selectedAsset = $isAgencyOauth && !empty($forms[$platformValue]['agency_social_asset_id'])
        ? $assets->firstWhere('id', $forms[$platformValue]['agency_social_asset_id'])
        : null;
    $hasInternalNotes = filled($forms[$platformValue]['notes'] ?? null)
        || filled($forms[$platformValue]['api_notes'] ?? null);
@endphp

<form wire:submit="save('{{ $platformValue }}')" class="social-account-form">
    @if($isTikTok)
        <div class="social-static-field">
            <span class="form-lbl">Modalità di collegamento</span>
            <strong>Collegamento individuale con TikTok</strong>
            <span>Il cliente autorizza direttamente l'applicazione tramite TikTok.</span>
        </div>
    @else
        <div class="form-g social-strategy-field">
            <label class="form-lbl" for="social-strategy-{{ $platformValue }}">Modalità di collegamento</label>
            <select
                id="social-strategy-{{ $platformValue }}"
                wire:model.live="forms.{{ $platformValue }}.connection_strategy"
                class="form-sel w-100"
            >
                <option value="agency_oauth">Profilo Meta gestito dall'agenzia</option>
                <option value="manual_token_config">Configurazione manuale</option>
            </select>
            <span class="social-field-help">La modalità agenzia usa una pagina o un account già sincronizzato in Connessioni Meta.</span>
        </div>
    @endif

    @if($isManual)
        <section class="social-form-section" aria-labelledby="manual-profile-{{ $platformValue }}">
            <div class="social-section-heading">
                <h5 id="manual-profile-{{ $platformValue }}">Dati del profilo</h5>
                <span>Configurazione avanzata</span>
            </div>

            <div class="form-row">
                <div class="form-g">
                    <label class="form-lbl" for="account-exists-{{ $platformValue }}">Il profilo esiste già?</label>
                    <select id="account-exists-{{ $platformValue }}" wire:model="forms.{{ $platformValue }}.account_exists" class="form-sel w-100">
                        @foreach($existsOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-g">
                    <label class="form-lbl" for="account-name-{{ $platformValue }}">Nome pagina o profilo</label>
                    <input id="account-name-{{ $platformValue }}" type="text" wire:model="forms.{{ $platformValue }}.account_name" class="form-in w-100">
                </div>
            </div>

            <div class="form-row">
                <div class="form-g">
                    <label class="form-lbl" for="username-{{ $platformValue }}">Nome utente</label>
                    <input id="username-{{ $platformValue }}" type="text" wire:model="forms.{{ $platformValue }}.username" class="form-in w-100">
                </div>
                <div class="form-g">
                    <label class="form-lbl" for="account-url-{{ $platformValue }}">Link pubblico</label>
                    <input id="account-url-{{ $platformValue }}" type="url" wire:model="forms.{{ $platformValue }}.account_url" class="form-in w-100" placeholder="https://...">
                    @error('forms.'.$platformValue.'.account_url')
                        <div class="u-text-xs u-text-red u-mt-xs">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-g">
                    <label class="form-lbl" for="access-status-{{ $platformValue }}">Stato del collegamento</label>
                    <select id="access-status-{{ $platformValue }}" wire:model="forms.{{ $platformValue }}.access_status" class="form-sel w-100">
                        @foreach($accessStatuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-g">
                    <label class="form-lbl" for="access-method-{{ $platformValue }}">Modalità di accesso</label>
                    <select id="access-method-{{ $platformValue }}" wire:model="forms.{{ $platformValue }}.access_method" class="form-sel w-100">
                        @foreach($accessMethods as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-g">
                    <label class="form-lbl" for="credential-location-{{ $platformValue }}">Dove sono conservate le credenziali?</label>
                    <input id="credential-location-{{ $platformValue }}" type="text" wire:model="forms.{{ $platformValue }}.credential_location" class="form-in w-100" placeholder="es. gestore password aziendale">
                </div>
                @if($isMeta)
                    <div class="form-g">
                        <label class="form-lbl" for="business-manager-{{ $platformValue }}">Business Manager ID</label>
                        <input id="business-manager-{{ $platformValue }}" type="text" wire:model="forms.{{ $platformValue }}.business_manager_id" class="form-in w-100">
                    </div>
                @endif
            </div>

            <div class="form-g social-ready-toggle">
                <label class="form-check-lbl">
                    <input type="checkbox" wire:model="forms.{{ $platformValue }}.is_ready_to_publish" class="social-ready-checkbox">
                    <span>Account pronto per la pubblicazione</span>
                </label>
            </div>
        </section>
    @elseif($isPlatformOauth)
        <section class="social-form-section" aria-labelledby="tiktok-connection-heading">
            <div class="social-section-heading">
                <h5 id="tiktok-connection-heading">Connessione TikTok</h5>
                <span>{{ $isConnected ? 'Autorizzazione presente' : 'Autorizzazione richiesta' }}</span>
            </div>

            @if($isConnected)
                <div class="social-connection-summary is-ready">
                    <i data-lucide="check-circle" class="u-icon-md"></i>
                    <div class="social-connection-summary-copy">
                        <strong>Account TikTok collegato</strong>
                        <span>{{ $account->account_name ?: $account->provider_account_name ?: 'Profilo autorizzato' }}</span>
                        <small>L'autorizzazione è salvata. Username e link pubblico non sono richiesti per pubblicare.</small>
                    </div>
                    <span class="badge {{ $isReady ? 'badge-success' : 'badge-warning' }}">
                        {{ $isReady ? 'Bozze abilitate' : 'Verifica necessaria' }}
                    </span>
                </div>

                @unless($isReady)
                    <div class="u-alert-warning social-state-message">
                        <i data-lucide="alert-triangle" class="u-icon-sm"></i>
                        <span>Il collegamento esiste, ma TikTok non ha ancora confermato tutte le capacità necessarie alla pubblicazione.</span>
                    </div>
                @endunless

                <div class="social-danger-zone">
                    <div>
                        <strong>Revoca collegamento</strong>
                        <span>Rimuove l'autorizzazione TikTok da questo cliente. Non elimina contenuti pubblicati.</span>
                    </div>
                    <button
                        type="button"
                        wire:click="disconnectOauth('{{ $platformValue }}')"
                        class="btn btn-red"
                        wire:confirm="Scollegare definitivamente questo account TikTok?"
                    >
                        Scollega TikTok
                    </button>
                </div>
            @else
                <div class="u-alert-warning social-state-message">
                    <i data-lucide="alert-triangle" class="u-icon-sm"></i>
                    <div>
                        <strong>Account TikTok non collegato</strong>
                        <span>Salva prima i dati essenziali del profilo, quindi avvia l'autorizzazione TikTok.</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-g">
                        <label class="form-lbl" for="account-exists-{{ $platformValue }}">Il profilo esiste già?</label>
                        <select id="account-exists-{{ $platformValue }}" wire:model="forms.{{ $platformValue }}.account_exists" class="form-sel w-100">
                            @foreach($existsOptions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-g">
                        <label class="form-lbl" for="account-name-{{ $platformValue }}">Nome del profilo TikTok</label>
                        <input id="account-name-{{ $platformValue }}" type="text" wire:model="forms.{{ $platformValue }}.account_name" class="form-in w-100">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-g">
                        <label class="form-lbl" for="username-{{ $platformValue }}">Nome utente, se conosciuto</label>
                        <input id="username-{{ $platformValue }}" type="text" wire:model="forms.{{ $platformValue }}.username" class="form-in w-100">
                    </div>
                    <div class="form-g">
                        <label class="form-lbl" for="account-url-{{ $platformValue }}">Link pubblico, se disponibile</label>
                        <input id="account-url-{{ $platformValue }}" type="url" wire:model="forms.{{ $platformValue }}.account_url" class="form-in w-100" placeholder="https://...">
                        @error('forms.'.$platformValue.'.account_url')
                            <div class="u-text-xs u-text-red u-mt-xs">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                @if($account)
                    <button type="button" wire:click="startTikTokOauth('{{ $platformValue }}')" class="btn btn-p u-inline-flex u-items-center u-gap-xs">
                        <i data-lucide="link" class="u-icon-sm"></i>
                        Collega TikTok
                    </button>
                @else
                    <div class="social-connect-pending">
                        <button type="button" class="btn btn-p" disabled>Collega TikTok</button>
                        <span>Il collegamento diventa disponibile dopo il primo salvataggio.</span>
                    </div>
                @endif
            @endif
        </section>
    @elseif($isAgencyOauth)
        <section class="social-form-section" aria-labelledby="agency-profile-{{ $platformValue }}">
            <div class="social-section-heading">
                <h5 id="agency-profile-{{ $platformValue }}">Profilo Meta assegnato</h5>
                <span>Gestito dall'agenzia</span>
            </div>

            @if($assets->isEmpty())
                <div class="u-alert-warning social-state-message">
                    <i data-lucide="alert-triangle" class="u-icon-sm"></i>
                    <div>
                        <strong>Nessun profilo {{ $platformValue === 'facebook' ? 'Facebook' : 'Instagram' }} disponibile</strong>
                        <span>Sincronizza prima i profili nella pagina Connessioni Meta dell'agenzia.</span>
                        @can('manage_social_connections')
                            <a href="{{ route('admin.social.connections.index') }}" class="social-inline-link">Apri Connessioni Meta</a>
                        @endcan
                    </div>
                </div>
            @else
                <div class="form-g social-asset-selector">
                    <label class="form-lbl" for="agency-asset-{{ $platformValue }}">Profilo da assegnare al cliente</label>
                    <select
                        id="agency-asset-{{ $platformValue }}"
                        wire:model.live="forms.{{ $platformValue }}.agency_social_asset_id"
                        wire:change="validateAssetAssignment('{{ $platformValue }}', $event.target.value)"
                        class="form-sel w-100"
                    >
                        <option value="">Nessun profilo assegnato</option>
                        @foreach($assets as $asset)
                            @php
                                $statusLabel = [];
                                if(!$asset->is_active) $statusLabel[] = 'Disattivato';
                                if($asset->revoked_at) $statusLabel[] = 'Revocato';
                                if($asset->connection && $asset->connection->requires_reauth) $statusLabel[] = 'Collegamento da rinnovare';
                                $statusStr = !empty($statusLabel) ? ' [' . implode(', ', $statusLabel) . ']' : '';
                            @endphp
                            <option value="{{ $asset->id }}">
                                {{ $asset->name }}{{ $asset->username ? ' (@' . $asset->username . ')' : '' }} — {{ $asset->connection->provider_user_name ?? 'Account Meta' }}{{ $statusStr }}
                            </option>
                        @endforeach
                    </select>
                    <span class="social-field-help">La selezione determina il profilo usato per le pubblicazioni di questo cliente.</span>
                </div>
            @endif

            @if($selectedAsset)
                @if(!$selectedAsset->is_active || $selectedAsset->revoked_at || ($selectedAsset->connection && $selectedAsset->connection->requires_reauth) || !$selectedAsset->is_assignable)
                    <div class="u-alert-error social-state-message">
                        <i data-lucide="alert-triangle" class="u-icon-sm"></i>
                        <span><strong>Collegamento non disponibile.</strong> Seleziona un altro profilo o rinnova la connessione Meta.</span>
                    </div>
                @endif

                <div class="social-selected-asset">
                    @if(isset($selectedAsset->raw_payload['picture']['data']['url']) || isset($selectedAsset->raw_payload['profile_picture_url']))
                        <img
                            src="{{ $selectedAsset->raw_payload['profile_picture_url'] ?? $selectedAsset->raw_payload['picture']['data']['url'] }}"
                            alt="Profilo {{ $selectedAsset->name }}"
                            class="social-selected-asset-image"
                        >
                    @else
                        <div class="social-selected-asset-placeholder" aria-hidden="true">
                            <i data-lucide="image" class="u-icon-md"></i>
                        </div>
                    @endif
                    <div class="social-selected-asset-copy">
                        <span class="form-lbl">Profilo selezionato</span>
                        <strong>{{ $selectedAsset->name }}</strong>
                        <span>{{ $selectedAsset->username ? '@' . $selectedAsset->username : 'Nome utente non fornito da Meta' }}</span>
                        <div class="social-selected-asset-badges">
                            <span class="badge badge-info">{{ $selectedAsset->asset_type->label() }}</span>
                            <span class="badge {{ $selectedAsset->publishing_status?->value === 'ready' ? 'badge-success' : 'badge-warning' }}">
                                {{ $selectedAsset->publishing_status?->label() ?? 'Stato non disponibile' }}
                            </span>
                        </div>
                    </div>
                </div>

                @if($account?->api_status === \App\Enums\Social\SocialApiStatus::Connected)
                    <div class="social-danger-zone">
                        <div>
                            <strong>Rimuovi assegnazione</strong>
                            <span>Il profilo resta collegato all'agenzia, ma non sarà più associato a questo cliente.</span>
                        </div>
                        <button
                            type="button"
                            wire:click="disconnect('{{ $platformValue }}')"
                            class="btn btn-red"
                            wire:loading.attr="disabled"
                            wire:confirm="Rimuovere il profilo social assegnato a questo cliente?"
                        >
                            Rimuovi assegnazione
                        </button>
                    </div>
                @endif
            @endif
        </section>
    @endif

    <details class="social-notes-disclosure">
        <summary>
            <span>Note interne facoltative</span>
            @if($hasInternalNotes)
                <span class="badge badge-info">Presenti</span>
            @endif
        </summary>
        <div class="social-notes-grid">
            <div class="form-g">
                <label class="form-lbl" for="account-notes-{{ $platformValue }}">Note sul profilo</label>
                <textarea id="account-notes-{{ $platformValue }}" wire:model="forms.{{ $platformValue }}.notes" class="form-ta w-100" rows="2" placeholder="Informazioni utili per il team"></textarea>
            </div>
            @unless($isManual)
                <div class="form-g">
                    <label class="form-lbl" for="connection-notes-{{ $platformValue }}">Note tecniche sul collegamento</label>
                    <textarea id="connection-notes-{{ $platformValue }}" wire:model="forms.{{ $platformValue }}.api_notes" class="form-ta w-100" rows="2" placeholder="Informazioni tecniche facoltative"></textarea>
                </div>
            @endunless
        </div>
    </details>

    <div class="form-actions social-form-actions">
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
                <i data-lucide="save" class="u-icon-sm"></i>
                @if($isPlatformOauth && $isConnected)
                    Salva note TikTok
                @elseif($isPlatformOauth)
                    Salva profilo TikTok
                @elseif($isAgencyOauth)
                    Salva assegnazione
                @else
                    Salva configurazione
                @endif
            </span>
            <span class="u-flex u-align-center u-gap-xs" wire:loading wire:target="save('{{ $platformValue }}')">
                <i data-lucide="loader" class="u-icon-sm icon-spin"></i> Salvataggio...
            </span>
        </button>
    </div>
</form>
