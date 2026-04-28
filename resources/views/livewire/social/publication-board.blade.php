<div>
    <x-page-header eyebrow="Social">
        <x-slot:title><strong>Bacheca Pubblicazioni</strong></x-slot:title>
    </x-page-header>

    <div class="filters-row">
        <input type="text" wire:model.live="search" class="form-in" placeholder="Cerca post o cliente..." style="width:250px">
    </div>

    <div class="g-3col">
        @forelse($posts as $post)
            <x-panel>
                <div style="display:flex; flex-direction:column; height: 100%;">
                    <div style="flex:1;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                            <span class="badge ba">
                                {{ $post->marketingProject->client->name ?? 'Nessun Cliente' }}
                            </span>
                            <span style="font-family:var(--mono); font-size:10px; color:var(--text3);">
                                {{ $post->created_at->format('d/m/Y') }}
                            </span>
                        </div>

                        <h3 style="font-size:16px; margin-bottom:10px; color:var(--text);">{{ $post->title }}</h3>
                        
                        @if($post->editorialPlanSlot)
                            <div style="font-size:13px; color:var(--text2); margin-bottom:15px;">
                                <strong style="color:var(--text);">Data prevista:</strong> {{ $post->editorialPlanSlot->scheduled_date?->format('d/m/Y') }} {{ \Carbon\Carbon::parse($post->editorialPlanSlot->scheduled_time)->format('H:i') }}
                            </div>
                        @endif

                        <div style="margin-bottom:15px;">
                            <div style="font-family:var(--mono); font-size:10px; color:var(--text3); margin-bottom:6px; letter-spacing:.04em; text-transform:uppercase;">Piattaforme & Accessi</div>
                            <div style="display:flex; flex-direction:column; gap:6px;">
                                @php
                                    $platforms = $post->editorialPlanSlot ? $post->editorialPlanSlot->platforms : ($post->marketingProject->platforms ?? []);
                                    $client = $post->marketingProject->client;
                                    $allReady = true;
                                    $tiktokWarning = false;
                                @endphp
                                @foreach($platforms ?? [] as $plat)
                                    @php
                                        $acc = $client?->socialAccountFor($plat);
                                        $status = $acc?->access_status ?? \App\Enums\Social\SocialAccessStatus::NotStarted;
                                        $color = $status->badgeColor();
                                        
                                        if ($plat !== 'tiktok' && !($acc?->isReadyToPublish() ?? false)) {
                                            $allReady = false;
                                        }
                                        if ($plat === 'tiktok' && !($acc?->isReadyToPublish() ?? false)) {
                                            $tiktokWarning = true;
                                        }
                                    @endphp
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; background:var(--bg); border:1px solid var(--line2); padding:6px 8px; border-radius:4px;">
                                        <div style="display:flex; flex-direction:column; gap:4px;">
                                            <div style="display:flex; align-items:center; gap:6px;">
                                                @if($plat === 'tiktok')
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text2);"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg>
                                                @else
                                                    <i data-lucide="{{ $plat }}" style="width:12px; height:12px; color:var(--text2);"></i>
                                                @endif
                                                <span style="font-size:11px; font-family:var(--sans); font-weight:500;">{{ ucfirst($plat) }}</span>
                                            </div>
                                            @if($acc)
                                            <div style="font-size:9px; color:var(--text3); font-family:var(--mono);">
                                                BM: {{ $acc->business_manager_id ?? 'N/A' }} | Metodo: {{ $acc->access_method?->label() ?? 'N/A' }}
                                                @if($acc->notes)
                                                    <br><span style="color:var(--orange)">Note: {{ str($acc->notes)->limit(30) }}</span>
                                                @endif
                                            </div>
                                            @endif
                                        </div>
                                        <div style="display:flex; align-items:center; gap:6px;" title="Metodo: {{ $acc?->access_method?->label() ?? 'Sconosciuto' }}">
                                            <span style="font-family:var(--mono); font-size:9px; padding:2px 4px; border-radius:3px; background:{{ $color }}15; color:{{ $color }}; border:1px solid {{ $color }}30;">
                                                {{ strtoupper($status->label()) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            
                            @if(!$allReady)
                                <div style="margin-top:8px; padding:6px; border-radius:4px; background:var(--orange)20; border:1px solid var(--orange)40; font-size:10px; color:var(--orange); display:flex; gap:4px;">
                                    <i data-lucide="alert-triangle" style="width:12px; height:12px; flex-shrink:0;"></i>
                                    <span>Attenzione: una o più piattaforme OBBLIGATORIE non sono pronte operativamente.</span>
                                </div>
                            @elseif($tiktokWarning)
                                <div style="margin-top:8px; padding:6px; border-radius:4px; background:var(--text3)15; border:1px solid var(--line2); font-size:10px; color:var(--text2); display:flex; gap:4px;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg>
                                    <span>Avviso: TikTok non è pronto. Pubblicazione consentita perché piattaforma opzionale.</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div style="border-top:1px solid var(--line); padding-top:15px; margin-top:15px; display:flex; flex-direction:column; gap:10px;">
                        @php
                            $isMetaReady = $client ? $client->isMetaReady() : false;
                            $requiresMeta = collect($platforms ?? [])->intersect(['facebook', 'instagram'])->isNotEmpty();
                            
                            $canPublish = !$requiresMeta || $isMetaReady;
                        @endphp
                        
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            @if($post->currentVersion && $post->currentVersion->caption)
                            <button type="button" class="btn btn-sm btn-secondary" onclick="navigator.clipboard.writeText(`{{ addslashes(str_replace('`', '\`', $post->currentVersion->caption)) }}`); alert('Caption copiata!')">
                                Copia Caption
                            </button>
                            @endif
                            @if($post->currentVersion && $post->currentVersion->image_path)
                            <a href="{{ Storage::url($post->currentVersion->image_path) }}" download class="btn btn-sm btn-secondary" target="_blank">
                                Scarica Media
                            </a>
                            @endif
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            @foreach($platforms ?? [] as $plat)
                                @php
                                    $acc = $client?->socialAccountFor($plat);
                                    $url = $acc?->account_url;
                                    if (!$url) {
                                        if ($plat === 'facebook') $url = 'https://business.facebook.com/';
                                        elseif ($plat === 'instagram') $url = 'https://business.facebook.com/creatorstudio/';
                                        elseif ($plat === 'tiktok') $url = 'https://ads.tiktok.com/business/';
                                    }
                                @endphp
                                <a href="{{ $url }}" target="_blank" class="btn btn-sm btn-secondary">Apri {{ ucfirst($plat) }}</a>
                            @endforeach
                            
                            @if($client)
                                <a href="{{ route('clients.show', $client) }}" class="btn btn-sm btn-secondary" style="margin-left:auto;">Accessi Cliente ↗</a>
                            @endif
                        </div>
                        
                        @if(!$canPublish)
                            <div style="padding:8px; background:var(--red)10; border:1px solid var(--red)30; border-radius:4px; font-size:11px; color:var(--red); display:flex; gap:6px;">
                                <i data-lucide="lock" style="width:14px; height:14px; flex-shrink:0;"></i>
                                <span>Pubblicazione bloccata: è richiesto l'accesso Meta Business.</span>
                            </div>
                        @endif

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px;">
                            <a href="{{ route('social.posts.show', $post->id) }}" wire:navigate class="btn btn-sm btn-g" style="border:none;">
                                Dettagli →
                            </a>
                            
                            <button wire:click="markAsPublished({{ $post->id }})" class="btn btn-sm btn-p" wire:loading.attr="disabled" {{ !$canPublish ? 'disabled' : '' }} onclick="return confirm('Hai effettivamente pubblicato il post sulle piattaforme previste?') || event.stopImmediatePropagation()">
                                Segna Pubblicato
                            </button>
                        </div>
                    </div>
                </div>
            </x-panel>
        @empty
            <div style="grid-column: 1 / -1;">
                <x-panel padded="true" style="text-align:center; padding:40px;">
                    <div style="color:var(--text3); margin-bottom:10px;"><i data-lucide="inbox" style="width:32px; height:32px; opacity:0.5;"></i></div>
                    <h3 style="color:var(--text); font-size:14px; margin-bottom:4px;">Nessun post da pubblicare</h3>
                    <p style="color:var(--text2); font-size:12px;">Tutti i post approvati sono stati pubblicati.</p>
                </x-panel>
            </div>
        @endforelse
    </div>

    <div style="margin-top:20px;">
        {{ $posts->links() }}
    </div>
</div>
