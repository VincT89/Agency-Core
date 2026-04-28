<div>
    <x-page-header eyebrow="Social">
        <x-slot:title><strong>Bacheca Pubblicazioni</strong></x-slot:title>
        <x-slot:actions>
            <div style="position: relative;">
                <i data-lucide="search" style="position: absolute; left: 10px; top: 10px; width: 14px; height: 14px; color: var(--text3);"></i>
                <input type="text" wire:model.live="search" class="form-in" placeholder="Cerca post o cliente..." style="padding-left: 32px; width: 250px; padding-top: 8px; padding-bottom: 8px;">
            </div>
        </x-slot:actions>
    </x-page-header>

    <x-panel>
        <table class="t-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Post</th>
                    <th style="width: 20%;">Cliente</th>
                    <th style="width: 35%;">Piattaforme e Accessi</th>
                    <th style="text-align: right; width: 20%;">Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($posts as $post)
                    @php
                        $platforms = $post->editorialPlanSlot ? $post->editorialPlanSlot->platforms : ($post->marketingProject->platforms ?? []);
                        $client = $post->marketingProject->client;
                        $allReady = true;
                        
                        foreach($platforms ?? [] as $plat) {
                            $acc = $client?->socialAccountFor($plat);
                            if ($plat !== 'tiktok' && !($acc?->isReadyToPublish() ?? false)) {
                                $allReady = false;
                            }
                        }
                        
                        $isMetaReady = $client ? $client->isMetaReady() : false;
                        $requiresMeta = collect($platforms ?? [])->intersect(['facebook', 'instagram'])->isNotEmpty();
                        $canPublish = !$requiresMeta || $isMetaReady;
                    @endphp

                    <tr>
                        <td class="name-col" onclick="window.location='{{ route('social.posts.show', $post->id) }}'" style="cursor: pointer;">
                            <div style="margin-bottom: 4px;">{{ $post->title }}</div>
                            @if($post->editorialPlanSlot)
                                <div class="social-index-meta">
                                    {{ $post->editorialPlanSlot->scheduled_date?->format('d/m/Y') }} {{ \Carbon\Carbon::parse($post->editorialPlanSlot->scheduled_time)->format('H:i') }}
                                </div>
                            @else
                                <div class="social-index-meta">Manuale</div>
                            @endif
                        </td>
                        
                        <td onclick="window.location='{{ route('social.posts.show', $post->id) }}'" style="cursor: pointer;">
                            <div>{{ $post->marketingProject->client->name ?? 'Nessun Cliente' }}</div>
                            <div class="social-index-meta">{{ $post->marketingProject->name ?? 'Nessun Progetto' }}</div>
                        </td>
                        
                        <td>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                @foreach($platforms ?? [] as $plat)
                                    @php
                                        $acc = $client?->socialAccountFor($plat);
                                        $status = $acc?->access_status ?? \App\Enums\Social\SocialAccessStatus::NotStarted;
                                        $color = $status->badgeColor();
                                        
                                        $url = $acc?->account_url;
                                        if (!$url) {
                                            if ($plat === 'facebook') $url = 'https://business.facebook.com/';
                                            elseif ($plat === 'instagram') $url = 'https://business.facebook.com/creatorstudio/';
                                            elseif ($plat === 'tiktok') $url = 'https://ads.tiktok.com/business/';
                                        }
                                    @endphp
                                    <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 4px; background: rgba(255,255,255,0.02); border: 1px solid var(--line2); font-size: 11px;">
                                        <a href="{{ $url }}" target="_blank" style="display: flex; align-items: center; gap: 4px; color: var(--text); text-decoration: none;" title="Apri {{ ucfirst($plat) }}">
                                            @if($plat === 'tiktok')
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text2);"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg>
                                            @else
                                                <i data-lucide="{{ $plat }}" style="width: 12px; height: 12px; color: var(--text2);"></i>
                                            @endif
                                            <span style="font-weight: 500; text-transform: capitalize;">{{ $plat }}</span>
                                        </a>
                                        <span style="font-family: var(--mono); font-size: 9px; padding: 1px 4px; border-radius: 3px; background: {{ $color }}15; color: {{ $color }}; border: 1px solid {{ $color }}30;" title="{{ $acc?->notes ?? 'Nessuna nota' }}">
                                            {{ strtoupper($status->label()) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                            @if(!$allReady)
                                <div style="margin-top: 6px; font-size: 10px; color: var(--orange); display: flex; align-items: center; gap: 4px;">
                                    <i data-lucide="alert-triangle" style="width: 10px; height: 10px;"></i>
                                    Attenzione: piattaforme non pronte.
                                </div>
                            @endif
                        </td>
                        
                        <td style="text-align: right; vertical-align: middle;">
                            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 8px;">
                                @if($post->currentVersion && $post->currentVersion->caption)
                                    <button type="button" class="btn btn-sm btn-secondary" style="padding: 4px 6px;" onclick="navigator.clipboard.writeText(`{{ addslashes(str_replace('`', '\`', $post->currentVersion->caption)) }}`); alert('Caption copiata!')" title="Copia Caption">
                                        <i data-lucide="copy" style="width: 12px; height: 12px;"></i>
                                    </button>
                                @endif
                                
                                @if($post->currentVersion?->preview_url)
                                    <a href="{{ $post->currentVersion->preview_url }}" download class="btn btn-sm btn-secondary" style="padding: 4px 6px;" target="_blank" title="Scarica Media">
                                        <i data-lucide="download" style="width: 12px; height: 12px;"></i>
                                    </a>
                                @endif
                                
                                <button wire:click="markAsPublished({{ $post->id }})" class="btn btn-sm btn-p" style="padding: 4px 10px;" wire:loading.attr="disabled" {{ !$canPublish ? 'disabled' : '' }} onclick="return confirm('Confermi di aver pubblicato manualmente il post sulle piattaforme previste?') || event.stopImmediatePropagation()">
                                    Pubblicato
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="social-empty-state" style="border: none;">
                            Nessun post da pubblicare.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        @if($posts->hasPages())
            <div style="padding: 15px; border-top: 1px solid var(--line);">
                {{ $posts->links() }}
            </div>
        @endif
    </x-panel>
</div>
