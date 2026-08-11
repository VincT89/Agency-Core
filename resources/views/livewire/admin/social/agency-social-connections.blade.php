<div>
    <x-page-header eyebrow="Social Media" title="Connessioni Meta dell'agenzia">
        @if($connections->isNotEmpty())
        <x-slot name="actions">
            <a href="{{ route('admin.social.connections.meta.redirect') }}" class="btn btn-p u-flex u-items-center u-gap-xs">
                <i data-lucide="plus" class="u-icon-sm"></i> Aggiungi account Meta
            </a>
        </x-slot>
        @endif
    </x-page-header>

    <div class="social-context-strip">
        <div>
            <strong>Qui si gestiscono soltanto le connessioni Meta condivise dall'agenzia.</strong>
            <span>Facebook e Instagram vengono sincronizzati qui e poi assegnati ai clienti. TikTok viene invece collegato dalla scheda del singolo cliente.</span>
        </div>
        <a href="{{ route('clients.index') }}" class="btn btn-g btn-sm">Apri elenco clienti</a>
    </div>

    @if($connections->isEmpty())
        <x-panel padded class="u-text-center u-p-xl">
            <div class="u-flex u-flex-col u-items-center u-gap-md social-empty-state">
                <i data-lucide="share-2" class="social-empty-icon"></i>
                <h3 class="u-text-strong social-empty-title">Nessuna connessione Meta attiva</h3>
                <p class="u-text-muted social-empty-text">Collega un account Meta aziendale per sincronizzare pagine Facebook e account Instagram Business.</p>
                <a href="{{ route('admin.social.connections.meta.redirect') }}" class="btn btn-p u-mt-lg u-flex u-items-center u-gap-xs social-empty-btn">
                    <i data-lucide="plus" class="u-icon-sm"></i> Collega account Meta
                </a>
            </div>
        </x-panel>
    @else
        <div class="social-grid">
            @foreach($connections as $connection)
                <x-panel padded class="social-card {{ $connection->status->value === 'connected' ? 'is-connected' : '' }}">
                    <div class="social-card-header">
                        <div class="social-card-title-row">
                            <h3 class="u-text-strong social-card-title">
                                {{ $connection->provider_user_name ?? 'Account Sconosciuto' }}
                            </h3>
                            <span class="badge {{ $connection->status->value === 'connected' && !$connection->requires_reauth ? 'badge-success' : 'badge-error' }}">
                                {{ $connection->requires_reauth ? 'Da ricollegare' : $connection->status->label() }}
                            </span>
                        </div>
                        <p class="u-text-sm u-text-muted social-card-provider">
                            <i data-lucide="facebook" class="u-icon-xs"></i> Connessione Meta
                        </p>
                    </div>

                    @if($connection->requires_reauth)
                        <div class="u-alert-error u-mb-md u-text-sm social-card-alert">
                            <div class="u-flex u-gap-sm u-items-start">
                                <i data-lucide="alert-triangle" class="u-icon-sm"></i>
                                <div>
                                    Il collegamento non è più valido. <br>
                                    <strong>Ricollega l'account Meta per continuare.</strong>
                                </div>
                            </div>
                        </div>
                    @endif

                    <dl class="social-card-metadata">
                        <div>
                            <dt>Profili disponibili</dt>
                            <dd>{{ $connection->assets->count() }}</dd>
                        </div>
                        <div>
                            <dt>Ultimo aggiornamento</dt>
                            <dd>{{ $connection->last_sync_at ? $connection->last_sync_at->format('d/m/Y H:i') : 'Mai' }}</dd>
                        </div>
                        <div>
                            <dt>Collegamento valido fino al</dt>
                            <dd>{{ $connection->token_expires_at ? $connection->token_expires_at->format('d/m/Y H:i') : 'Senza scadenza nota' }}</dd>
                        </div>
                    </dl>

                    <div class="u-flex u-justify-between u-items-center social-card-actions">
                        <button wire:click="syncConnection({{ $connection->id }})" 
                                wire:loading.attr="disabled"
                                class="btn btn-outline btn-sm u-flex u-items-center u-gap-xs">
                            <span class="u-flex u-items-center u-gap-xs" wire:loading.remove wire:target="syncConnection({{ $connection->id }})">
                                <i data-lucide="refresh-cw" class="u-icon-xs"></i> Sincronizza profili
                            </span>
                            <span class="u-flex u-items-center u-gap-xs" wire:loading wire:target="syncConnection({{ $connection->id }})">
                                <i data-lucide="loader" class="u-icon-xs icon-spin"></i> Aggiornamento...
                            </span>
                        </button>
                        
                        <button type="button"
                                wire:confirm="Revocare questa connessione Meta? I profili associati non saranno più disponibili per i clienti collegati."
                                wire:click="revokeConnection({{ $connection->id }})" 
                                wire:loading.attr="disabled"
                                class="btn btn-red btn-sm u-flex u-items-center u-gap-xs">
                            <span class="u-flex u-items-center u-gap-xs" wire:loading.remove wire:target="revokeConnection({{ $connection->id }})">
                                <i data-lucide="unlink" class="u-icon-xs"></i> Revoca connessione
                            </span>
                            <span class="u-flex u-items-center u-gap-xs" wire:loading wire:target="revokeConnection({{ $connection->id }})">
                                <i data-lucide="loader" class="u-icon-xs icon-spin"></i> Disconnessione...
                            </span>
                        </button>
                    </div>
                </x-panel>
            @endforeach
        </div>
        
        <x-panel padded>
            <h3 class="u-text-strong u-mb-md">Pagine Facebook e profili Instagram disponibili</h3>
            <div class="social-table-container social-assets-table-container">
                <table class="t-table u-w-full social-assets-table">
                    <thead>
                        <tr>
                            <th>Profilo</th>
                            <th>Piattaforma</th>
                            <th>Account collegato</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($connections->flatMap->assets as $asset)
                            <tr class="social-assets-row">
                                <td class="social-assets-profile" data-label="Profilo">
                                    <div class="u-flex u-items-center u-gap-sm">
                                        @if(isset($asset->raw_payload['picture']['data']['url']) || isset($asset->raw_payload['profile_picture_url']))
                                            <img src="{{ $asset->raw_payload['profile_picture_url'] ?? $asset->raw_payload['picture']['data']['url'] }}" alt="" class="social-asset-img">
                                        @else
                                            <div class="avatar-sm"><i data-lucide="image" class="u-icon-xs"></i></div>
                                        @endif
                                        <div>
                                            <div class="u-text-strong">{{ $asset->name }}</div>
                                            <div class="u-text-xs u-text-muted">{{ $asset->username ? '@' . $asset->username : 'Nome utente non disponibile' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Piattaforma">
                                    <span class="badge badge-info">
                                        {{ $asset->asset_type->label() }}
                                    </span>
                                </td>
                                <td class="u-text-secondary" data-label="Account collegato">
                                    {{ $asset->connection->provider_user_name ?? 'Sconosciuto' }}
                                </td>
                                <td data-label="Stato">
                                    @if($asset->is_active)
                                        <span class="badge badge-success">Attivo</span>
                                    @else
                                        <span class="badge badge-error">Revocato</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="social-assets-empty-row">
                                <td colspan="4" class="u-text-center u-text-muted u-p-lg">Nessun profilo disponibile.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif
</div>
