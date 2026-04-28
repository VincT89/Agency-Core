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
                            <div style="font-family:var(--mono); font-size:10px; color:var(--text3); margin-bottom:6px; letter-spacing:.04em; text-transform:uppercase;">Piattaforme</div>
                            <div style="display:flex; gap:5px;">
                                @php
                                    $platforms = $post->editorialPlanSlot ? $post->editorialPlanSlot->platforms : ($post->marketingProject->platforms ?? []);
                                @endphp
                                @foreach($platforms ?? [] as $plat)
                                    <span class="badge bd">{{ ucfirst($plat) }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div style="border-top:1px solid var(--line); padding-top:15px; margin-top:15px; display:flex; flex-direction:column; gap:10px;">
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
                            @php
                                $platforms = $post->editorialPlanSlot ? $post->editorialPlanSlot->platforms : ($post->marketingProject->platforms ?? []);
                            @endphp
                            @if(in_array('facebook', $platforms ?? []))
                            <a href="https://business.facebook.com/" target="_blank" class="btn btn-sm btn-secondary">Apri Facebook</a>
                            @endif
                            @if(in_array('instagram', $platforms ?? []))
                            <a href="https://business.facebook.com/creatorstudio/" target="_blank" class="btn btn-sm btn-secondary">Apri Instagram</a>
                            @endif
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px;">
                            <a href="{{ route('social.posts.show', $post->id) }}" wire:navigate class="btn btn-sm btn-g" style="border:none;">
                                Dettagli →
                            </a>
                            
                            <button wire:click="markAsPublished({{ $post->id }})" class="btn btn-sm btn-p" wire:loading.attr="disabled" onclick="return confirm('Hai effettivamente pubblicato il post sulle piattaforme previste?') || event.stopImmediatePropagation()">
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
