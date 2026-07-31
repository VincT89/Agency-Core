<div>
    <x-page-header eyebrow="Amministrazione Social">
        <x-slot:title><strong>Coda Social</strong> Pubblicazioni</x-slot:title>
        <div class="u-text-sm u-text-muted u-mt-xs">Monitora i post falliti, scaduti o in attesa di revisione manuale.
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

    <div class="u-flex u-gap-md u-mb-md">
        <button wire:click="$set('filter', 'all')" class="btn {{ $filter === 'all' ? 'btn-p' : 'btn-sec' }}">Tutti</button>
        <button wire:click="$set('filter', 'needs_manual_review')" class="btn {{ $filter === 'needs_manual_review' ? 'btn-orange' : 'btn-sec' }}">Da revisionare</button>
        <button wire:click="$set('filter', 'failed')" class="btn {{ $filter === 'failed' ? 'btn-red' : 'btn-sec' }}">Fallite</button>
        <button wire:click="$set('filter', 'stale_publishing')" class="btn {{ $filter === 'stale_publishing' ? 'btn-blue' : 'btn-sec' }}">Bloccate</button>
    </div>

    <div class="panel">
        <div class="table-responsive">
            <table class="t-table u-w-full">
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
                        <tr>
                            <td>
                                <strong>#{{ $pub->id }}</strong><br>
                                <span class="u-text-meta muted">{{ $pub->created_at->format('d/m/Y H:i') }}</span>
                            </td>
                            <td>
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
                            <td>
                                <span class="u-text-transform-capitalize badge badge-subtle">{{ $pub->platform->value }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $pub->status->badgeClass() }}">
                                    {{ $pub->status->label() }}
                                </span>
                            </td>
                            <td class="u-max-w-xs">
                                @if($pub->error_message || in_array($pub->status, [\App\Enums\Social\PublicationStatus::Failed, \App\Enums\Social\PublicationStatus::NeedsManualReview], true))
                                    <div class="u-text-sm u-text-red">Pubblicazione non completata. Controlla l'account collegato o riprova.</div>
                                @else
                                    <div class="u-text-sm u-text-muted">Nessun problema rilevato.</div>
                                @endif
                            </td>
                            <td class="u-text-right">
                                <div class="u-flex u-justify-end u-gap-xs">
                                    @if(in_array($pub->status, [\App\Enums\Social\PublicationStatus::Publishing]))
                                        <button wire:click="refreshPublication({{ $pub->id }})" class="btn-xs btn-outline-primary" title="Aggiorna lo stato della pubblicazione">
                                            <i class="fas fa-sync-alt"></i> Aggiorna stato
                                        </button>
                                    @endif
                                    @if(in_array($pub->status, [\App\Enums\Social\PublicationStatus::Failed, \App\Enums\Social\PublicationStatus::NeedsManualReview], true) && $pub->platform === \App\Enums\Social\SocialPlatform::Instagram)
                                        <button 
                                            wire:confirm="Verrà creato un nuovo tentativo. Prima di continuare, verifica che il contenuto non sia già visibile su Instagram per evitare duplicati."
                                            wire:click="retryPublication({{ $pub->id }})" 
                                            class="btn btn-p btn-xs">
                                            Riprova su Instagram
                                        </button>
                                    @elseif(in_array($pub->status, [\App\Enums\Social\PublicationStatus::Failed, \App\Enums\Social\PublicationStatus::NeedsManualReview], true) && $pub->platform === \App\Enums\Social\SocialPlatform::Tiktok)
                                        <button 
                                            wire:confirm="Verrà creato un nuovo tentativo di pubblicazione e il precedente sarà archiviato come superato. Procedere?"
                                            wire:click="retryPublication({{ $pub->id }})" 
                                            class="btn btn-p btn-xs">
                                            Riprova TikTok
                                        </button>
                                    @elseif(in_array($pub->status, [\App\Enums\Social\PublicationStatus::Failed, \App\Enums\Social\PublicationStatus::NeedsManualReview], true))
                                        <button 
                                            wire:click="retryPublication({{ $pub->id }})" 
                                            class="btn btn-p btn-xs">
                                            Riprova
                                        </button>
                                    @endif

                                    @if(in_array($pub->status, [\App\Enums\Social\PublicationStatus::Pending, \App\Enums\Social\PublicationStatus::Publishing], true))
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
                        <tr>
                            <td colspan="6" class="u-text-center u-p-lg u-text-muted">
                                Nessuna pubblicazione in questa coda. Tutto funziona correttamente.
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
