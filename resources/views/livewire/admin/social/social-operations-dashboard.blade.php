<div>
    <x-page-header eyebrow="Amministrazione Social">
        <x-slot:title><strong>Coda Social</strong> Pubblicazioni</x-slot:title>
        <div class="u-text-sm u-text-muted u-mt-xs">Monitora le pubblicazioni operative. I post archiviati localmente restano consultabili nel filtro dedicato.
        </div>
    </x-page-header>

    @if (session()->has('success'))
        <div class="u-bg-green-50 u-border u-border-green-200 u-text-green-700 u-p-sm u-rounded u-mb-md">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('info'))
        <div class="u-bg-blue-50 u-border u-border-blue-200 u-text-blue-700 u-p-sm u-rounded u-mb-md">
            {{ session('info') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="u-bg-red-50 u-border u-border-red-200 u-text-red-700 u-p-sm u-rounded u-mb-md">
            {{ session('error') }}
        </div>
    @endif

    <div class="social-operation-filters" role="group" aria-label="Filtra le pubblicazioni social">
        <button type="button" wire:click="$set('filter', 'all')" aria-pressed="{{ $filter === 'all' ? 'true' : 'false' }}" class="btn {{ $filter === 'all' ? 'btn-p' : 'btn-sec' }}">Operative</button>
        <button type="button" wire:click="$set('filter', 'active')" aria-pressed="{{ $filter === 'active' ? 'true' : 'false' }}" class="btn {{ $filter === 'active' ? 'btn-blue' : 'btn-sec' }}">In corso</button>
        <button type="button" wire:click="$set('filter', 'published')" aria-pressed="{{ $filter === 'published' ? 'true' : 'false' }}" class="btn {{ $filter === 'published' ? 'btn-p' : 'btn-sec' }}">Pubblicate</button>
        <button type="button" wire:click="$set('filter', 'needs_manual_review')" aria-pressed="{{ $filter === 'needs_manual_review' ? 'true' : 'false' }}" class="btn {{ $filter === 'needs_manual_review' ? 'btn-orange' : 'btn-sec' }}">Da revisionare</button>
        <button type="button" wire:click="$set('filter', 'failed')" aria-pressed="{{ $filter === 'failed' ? 'true' : 'false' }}" class="btn {{ $filter === 'failed' ? 'btn-red' : 'btn-sec' }}">Fallite</button>
        <button type="button" wire:click="$set('filter', 'stale_publishing')" aria-pressed="{{ $filter === 'stale_publishing' ? 'true' : 'false' }}" class="btn {{ $filter === 'stale_publishing' ? 'btn-blue' : 'btn-sec' }}">Bloccate</button>
        <button type="button" wire:click="$set('filter', 'attempt_history')" aria-pressed="{{ $filter === 'attempt_history' ? 'true' : 'false' }}" class="btn {{ $filter === 'attempt_history' ? 'btn-p' : 'btn-sec' }}">Storico tentativi</button>
        <button type="button" wire:click="$set('filter', 'archived')" aria-pressed="{{ $filter === 'archived' ? 'true' : 'false' }}" class="btn {{ $filter === 'archived' ? 'btn-p' : 'btn-sec' }}">Archiviate</button>
    </div>

    <div class="panel social-operations-panel">
        <div class="table-responsive social-operations-table-container">
            <table class="t-table u-w-full social-operations-table">
                <thead>
                    <tr>
                        <th>ID / Data</th>
                        <th>Cliente / Post</th>
                        <th>Piattaforma</th>
                        <th>Stato</th>
                        <th>Esito</th>
                        <th class="u-text-right">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($publications as $pub)
                        <tr class="social-operation-row">
                            <td class="social-operation-id" data-label="ID / Data">
                                <strong>#{{ $pub->id }}</strong><br>
                                <span class="u-text-meta muted">{{ $pub->created_at->format('d/m/Y H:i') }}</span>
                            </td>
                            <td class="social-operation-post" data-label="Cliente / Post">
                                @if($pub->post && $pub->post->campaign && $pub->post->campaign->client)
                                    <div class="u-text-sm u-fw-bold">{{ $pub->post->campaign->client->name }}</div>
                                @endif
                                @if($pub->post && $pub->post->campaign)
                                    <a href="{{ route('marketing-campaigns.posts.show', ['campaign' => $pub->post->campaign->id, 'post' => $pub->post_id ?? $pub->marketing_campaign_post_id]) }}" class="u-text-meta u-text-accent u-no-underline">
                                        {{ \Illuminate\Support\Str::limit($pub->post->title ?? 'Senza Titolo', 30) }}
                                    </a>
                                @else
                                    <span class="u-text-meta muted">
                                        {{ \Illuminate\Support\Str::limit($pub->post->title ?? 'Post senza campagna (Orfano)', 30) }}
                                    </span>
                                @endif
                            </td>
                            <td class="social-operation-platform" data-label="Piattaforma">
                                <span class="u-text-transform-capitalize badge badge-subtle">{{ $pub->platform->value }}</span>
                            </td>
                            <td class="social-operation-status" data-label="Stato">
                                <span class="badge {{ $pub->status->badgeClass() }}">
                                    {{ $pub->status->label() }}
                                </span>
                            </td>
                            <td class="u-max-w-xs social-operation-result" data-label="Esito">
                                @if($pub->status === \App\Enums\Social\PublicationStatus::Published)
                                    <div class="u-text-sm u-text-green">Pubblicata correttamente.</div>
                                @elseif(in_array($pub->status, [\App\Enums\Social\PublicationStatus::Pending, \App\Enums\Social\PublicationStatus::Publishing], true))
                                    <div class="u-text-sm u-text-blue">Elaborazione in corso.</div>
                                @elseif($pub->error_message || in_array($pub->status, [\App\Enums\Social\PublicationStatus::Failed, \App\Enums\Social\PublicationStatus::NeedsManualReview], true))
                                    @include('components.social.publication-diagnostic', [
                                        'publication' => $pub,
                                        'panel' => false,
                                    ])
                                @else
                                    <div class="u-text-sm u-text-muted">Nessun problema rilevato.</div>
                                @endif
                            </td>
                            <td class="u-text-right social-operation-actions" data-label="Azioni">
                                <div class="u-flex u-flex-wrap u-justify-end u-gap-xs social-operation-actions-list">
                                    @php($externalPermalink = $pub->resolved_external_permalink)
                                    @if($pub->post && $pub->post->campaign)
                                        <a
                                            href="{{ route('marketing-campaigns.posts.show', ['campaign' => $pub->post->campaign->id, 'post' => $pub->post_id ?? $pub->marketing_campaign_post_id]) }}"
                                            class="btn btn-sec btn-xs"
                                            wire:navigate>
                                            Dettagli post
                                        </a>
                                    @endif
                                    @if($externalPermalink)
                                        <a
                                            href="{{ $externalPermalink }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn btn-p btn-xs social-operation-external-link">
                                            Apri sul social
                                        </a>
                                    @elseif(
                                        $pub->status === \App\Enums\Social\PublicationStatus::Published
                                        && in_array($pub->platform, [
                                            \App\Enums\Social\SocialPlatform::Instagram,
                                            \App\Enums\Social\SocialPlatform::Tiktok,
                                        ], true)
                                    )
                                        <button
                                            type="button"
                                            wire:click="recoverPermalink({{ $pub->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="recoverPermalink({{ $pub->id }})"
                                            class="btn btn-sec btn-xs social-operation-permalink-recovery">
                                            Recupera collegamento
                                        </button>
                                    @endif
                                    @if(in_array($pub->status, [\App\Enums\Social\PublicationStatus::Publishing]))
                                        <button type="button" wire:click="refreshPublication({{ $pub->id }})" class="btn-xs btn-outline-primary" title="Aggiorna lo stato della pubblicazione">
                                            <i class="fas fa-sync-alt"></i> Aggiorna stato
                                        </button>
                                    @endif
                                    @if(!$pub->post?->isArchived() && in_array($pub->status, [\App\Enums\Social\PublicationStatus::Failed, \App\Enums\Social\PublicationStatus::NeedsManualReview], true) && $pub->platform === \App\Enums\Social\SocialPlatform::Instagram)
                                        <button 
                                            wire:confirm="Verrà creato un nuovo tentativo. Prima di continuare, verifica che il contenuto non sia già visibile su Instagram per evitare duplicati."
                                            wire:click="retryPublication({{ $pub->id }})" 
                                            class="btn btn-p btn-xs">
                                            Riprova su Instagram
                                        </button>
                                    @elseif(!$pub->post?->isArchived() && in_array($pub->status, [\App\Enums\Social\PublicationStatus::Failed, \App\Enums\Social\PublicationStatus::NeedsManualReview], true) && $pub->platform === \App\Enums\Social\SocialPlatform::Tiktok)
                                        <button 
                                            wire:confirm="Verrà creato un nuovo tentativo di pubblicazione e il precedente sarà archiviato come superato. Procedere?"
                                            wire:click="retryPublication({{ $pub->id }})" 
                                            class="btn btn-p btn-xs">
                                            Riprova TikTok
                                        </button>
                                    @elseif(!$pub->post?->isArchived() && in_array($pub->status, [\App\Enums\Social\PublicationStatus::Failed, \App\Enums\Social\PublicationStatus::NeedsManualReview], true))
                                        <button 
                                            wire:click="retryPublication({{ $pub->id }})" 
                                            class="btn btn-p btn-xs">
                                            Riprova
                                        </button>
                                    @endif

                                    @if($pub->post?->isArchived())
                                        <button
                                            type="button"
                                            wire:confirm="Ripristinare il post e tutti i suoi tentativi nelle viste operative?"
                                            wire:click="restorePost({{ $pub->id }})"
                                            class="btn btn-p btn-xs">
                                            Ripristina post
                                        </button>
                                    @elseif($pub->post?->canBeArchived())
                                        <button
                                            type="button"
                                            wire:confirm="Il post e i tentativi collegati verranno nascosti dalle viste operative. Lo storico resterà conservato e nessun contenuto remoto verrà eliminato. Verifica prima che il contenuto non sia già visibile sul social. Procedere?"
                                            wire:click="archivePost({{ $pub->id }})"
                                            class="btn btn-red btn-xs">
                                            Archivia post
                                        </button>
                                    @endif

                                    @if(!$pub->post?->isArchived() && in_array($pub->status, [\App\Enums\Social\PublicationStatus::Pending, \App\Enums\Social\PublicationStatus::Publishing], true))
                                    <button 
                                        wire:confirm="Sei sicuro di voler forzare il fallimento definitivo di questa pubblicazione?"
                                        wire:click="forceFailPublication({{ $pub->id }})" 
                                        class="btn btn-red btn-xs">
                                        <i data-lucide="x" class="u-icon-sm"></i> Segna fallita
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="social-operation-empty-row">
                            <td colspan="6" class="u-text-center u-p-lg u-text-muted social-operation-empty">
                                Nessuna pubblicazione corrisponde al filtro selezionato.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="u-p-md u-border-t u-border-line">
            {{ $publications->links() }}
        </div>
    </div>
</div>
