<div>
    <x-page-header eyebrow="Social Media" title="Connessioni Social Agenzia">
        @if($connections->isNotEmpty())
        <x-slot name="actions">
            <a href="{{ route('admin.social.connections.meta.redirect') }}" class="btn btn-p u-flex u-items-center u-gap-xs">
                <i data-lucide="plus" class="u-icon-sm"></i> Collega Meta Agenzia
            </a>
        </x-slot>
        @endif
    </x-page-header>

    @if($connections->isEmpty())
        <x-panel padded class="u-text-center u-p-xl">
            <div class="u-flex u-flex-col u-items-center u-gap-md social-empty-state">
                <i data-lucide="share-2" class="social-empty-icon"></i>
                <h3 class="u-text-strong social-empty-title">Nessuna connessione attiva</h3>
                <p class="u-text-muted social-empty-text">Collega un account Meta aziendale per avviare la sincronizzazione automatica degli asset e renderli disponibili ai tuoi clienti.</p>
                <a href="{{ route('admin.social.connections.meta.redirect') }}" class="btn btn-p u-mt-lg u-flex u-items-center u-gap-xs social-empty-btn">
                    <i data-lucide="plus" class="u-icon-sm"></i> Collega Meta Agenzia
                </a>
            </div>
        </x-panel>
    @else
        <div class="social-grid">
            @foreach($connections as $connection)
                <x-panel padded class="social-card {{ $connection->status->value === 'connected' ? 'is-connected' : '' }}">
                    <div class="u-flex u-justify-between u-items-start u-mb-md">
                        <div>
                            <h3 class="u-text-strong social-card-title">
                                {{ $connection->provider_user_name ?? 'Account Sconosciuto' }}
                            </h3>
                            <p class="u-text-sm u-text-muted social-card-provider">
                                <i data-lucide="{{ $connection->provider }}" class="u-icon-xs"></i> {{ $connection->provider }}
                            </p>
                        </div>
                        <span class="badge {{ $connection->status->value === 'connected' && !$connection->requires_reauth ? 'badge-success' : 'badge-error' }}">
                            {{ $connection->requires_reauth ? 'Richiede Autenticazione' : $connection->status->label() }}
                        </span>
                    </div>

                    @if($connection->requires_reauth)
                        <div class="u-alert-error u-mb-md u-text-sm social-card-alert">
                            <div class="u-flex u-gap-sm u-items-start">
                                <i data-lucide="alert-triangle" class="u-icon-sm"></i>
                                <div>
                                    Il token di questa connessione è scaduto o revocato da Meta. <br>
                                    <strong>Devi ricollegare l'account!</strong>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="u-text-sm u-text-secondary u-mb-md">
                        <p><strong>Asset Trovati:</strong> {{ $connection->assets->count() }}</p>
                        <p><strong>Ultimo Sync:</strong> {{ $connection->last_sync_at ? $connection->last_sync_at->format('d/m/Y H:i') : 'Mai' }}</p>
                    </div>

                    <div class="u-flex u-justify-between u-items-center social-card-actions">
                        <button wire:click="syncConnection({{ $connection->id }})" 
                                wire:loading.attr="disabled"
                                class="btn btn-outline btn-sm u-flex u-items-center u-gap-xs">
                            <span class="u-flex u-items-center u-gap-xs" wire:loading.remove wire:target="syncConnection({{ $connection->id }})">
                                <i data-lucide="refresh-cw" class="u-icon-xs"></i> Sincronizza
                            </span>
                            <span class="u-flex u-items-center u-gap-xs" wire:loading wire:target="syncConnection({{ $connection->id }})">
                                <i data-lucide="loader" class="u-icon-xs icon-spin"></i> Sync...
                            </span>
                        </button>
                        
                        <button type="button" 
                                onclick="confirm('Sei sicuro di voler revocare questa connessione e rimuovere gli asset associati? L\'azione impatterà tutti i clienti ad essa collegati.') || event.stopImmediatePropagation()"
                                wire:click="revokeConnection({{ $connection->id }})" 
                                wire:loading.attr="disabled"
                                class="btn btn-error btn-sm u-flex u-items-center u-gap-xs">
                            <span class="u-flex u-items-center u-gap-xs" wire:loading.remove wire:target="revokeConnection({{ $connection->id }})">
                                <i data-lucide="trash-2" class="u-icon-xs"></i> Disconnetti
                            </span>
                            <span class="u-flex u-items-center u-gap-xs" wire:loading wire:target="revokeConnection({{ $connection->id }})">
                                <i data-lucide="loader" class="u-icon-xs icon-spin"></i> Revoca...
                            </span>
                        </button>
                    </div>
                </x-panel>
            @endforeach
        </div>
        
        <x-panel padded>
            <h3 class="u-text-strong u-mb-md">Dettaglio Asset Sincronizzati</h3>
            <div class="social-table-container">
                <table class="t-table u-w-full">
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Piattaforma</th>
                            <th>Connessione Origine</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($connections->flatMap->assets as $asset)
                            <tr>
                                <td>
                                    <div class="u-flex u-items-center u-gap-sm">
                                        @if(isset($asset->raw_payload['picture']['data']['url']) || isset($asset->raw_payload['profile_picture_url']))
                                            <img src="{{ $asset->raw_payload['profile_picture_url'] ?? $asset->raw_payload['picture']['data']['url'] }}" alt="" class="social-asset-img">
                                        @else
                                            <div class="avatar-sm"><i data-lucide="image" class="u-icon-xs"></i></div>
                                        @endif
                                        <div>
                                            <div class="u-text-strong">{{ $asset->name }}</div>
                                            <div class="u-text-xs u-text-muted">{{ $asset->username ? '@' . $asset->username : $asset->provider_asset_id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        {{ $asset->asset_type->label() }}
                                    </span>
                                </td>
                                <td class="u-text-secondary">
                                    {{ $asset->connection->provider_user_name ?? 'Sconosciuto' }}
                                </td>
                                <td>
                                    @if($asset->is_active)
                                        <span class="badge badge-success">Attivo</span>
                                    @else
                                        <span class="badge badge-error">Revocato</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="u-text-center u-text-muted u-p-lg">Nessun asset presente.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif
</div>
