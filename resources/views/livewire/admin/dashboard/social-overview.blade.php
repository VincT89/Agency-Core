<x-panel title="Social Overview" dot="var(--teal)" padded>
    <x-slot:headerActions>
        <a href="{{ route('social.posts.index') }}" class="social-header-link">
            Gestione Social <i data-lucide="arrow-right" style="width:14px; height:14px;"></i>
        </a>
    </x-slot:headerActions>

    {{-- KPI --}}
    <div class="social-kpi-grid">
        <div class="shoot-stat-card neutral">
            <div class="shoot-stat-val" style="color: var(--purple);">{{ $internalReviewCount }}</div>
            <div class="shoot-stat-lbl">In Rev. Interna</div>
        </div>
        <div class="shoot-stat-card danger">
            <div class="shoot-stat-val" style="color: var(--red);">{{ $regeneratingCount }}</div>
            <div class="shoot-stat-lbl">In Rigenerazione</div>
        </div>
        <div class="shoot-stat-card warn">
            <div class="shoot-stat-val" style="color: var(--orange);">{{ $clientChangesCount }}</div>
            <div class="shoot-stat-lbl">Modifiche Cliente</div>
        </div>
        <div class="shoot-stat-card success">
            <div class="shoot-stat-val" style="color: var(--teal);">{{ $sentToClientCount }}</div>
            <div class="shoot-stat-lbl">Inviati al Cliente</div>
        </div>
        <div class="shoot-stat-card success">
            <div class="shoot-stat-val" style="color: var(--green);">{{ $clientApprovedCount }}</div>
            <div class="shoot-stat-lbl">Approvati</div>
        </div>
        <div class="shoot-stat-card neutral">
            <div class="shoot-stat-val" style="color: var(--blue);">{{ $readyForClientCount }}</div>
            <div class="shoot-stat-lbl">Pronti x Cliente</div>
        </div>
    </div>

    <div class="split-layout" style="gap: 20px;">
        
        {{-- Lista Operativa --}}
        <div style="flex: 1;">
            <div class="social-section-title">Richiedono Attenzione</div>
            @if(count($attentionPosts) > 0)
                <table class="t-table" style="border-top:1px solid var(--line); margin-top:-1px;">
                    <thead>
                        <tr>
                            <th>Post</th>
                            <th>Stato</th>
                            <th style="text-align: right">Azione</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attentionPosts as $post)
                            <tr>
                                <td class="name-col">
                                    <div class="social-post-title">
                                        [{{ $post->client->name ?? 'N/D' }}] {{ $post->title }}
                                    </div>
                                    <div class="social-post-meta">{{ $post->updated_at->diffForHumans() }}</div>
                                </td>
                                <td><span class="badge" style="background: {{ $post->status->color() }}; font-size: 10px;">{{ $post->status->label() }}</span></td>
                                <td style="text-align: right">
                                    <span class="social-action-btn">Vedi</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="social-empty-state">
                    Nessun post critico in questo momento.
                </div>
            @endif
        </div>

        {{-- Alert --}}
        <div style="flex: 1;">
            <div class="social-section-title">Alert di Sistema</div>
            <div class="social-alerts-container">
                
                @foreach($staleSentToClient as $post)
                    <div class="social-alert-card yellow">
                        <div class="social-alert-title yellow">Inviato al cliente da > 48h senza risposta</div>
                        <div class="social-alert-link">{{ $post->title }}</div>
                    </div>
                @endforeach

                @foreach($staleRegenerating as $post)
                    <div class="social-alert-card red">
                        <div class="social-alert-title red">In rigenerazione da > 1h (Possibile blocco n8n)</div>
                        <div class="social-alert-link">{{ $post->title }}</div>
                    </div>
                @endforeach

                @foreach($staleInternalReview as $post)
                    <div class="social-alert-card purple">
                        <div class="social-alert-title purple">Fermo in revisione interna da > 24h</div>
                        <div class="social-alert-link">{{ $post->title }}</div>
                    </div>
                @endforeach

                @if($staleSentToClient->isEmpty() && $staleRegenerating->isEmpty() && $staleInternalReview->isEmpty())
                    <div class="social-empty-state">Nessun alert attivo. Tutto regolare.</div>
                @endif

            </div>
        </div>

    </div>
</x-panel>