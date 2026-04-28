<div>
    <x-page-header
        eyebrow="Modulo · Social"
        :meta="$posts->total() . ' totali'"
    >
        <x-slot:title><strong>Archivio Social Post</strong></x-slot:title>
    </x-page-header>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;justify-content:flex-end">
        <select wire:model.live="statusFilter" class="form-in" style="padding:5px 10px;font-size:11px;width:200px">
            <option value="">Tutti gli stati</option>
            @foreach(\App\Enums\Social\SocialPostStatus::cases() as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </select>
        @if($statusFilter)
            <button wire:click="$set('statusFilter', '')" class="btn btn-g" style="padding:5px 10px;font-size:11px">Reset</button>
        @endif
    </div>

    <x-panel>
        <table class="t-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Media</th>
                    <th>Titolo</th>
                    <th>Progetto</th>
                    <th>Stato</th>
                    <th>Ultima Modifica</th>
                    <th style="text-align: right">Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($posts as $post)
                    <tr onclick="window.location='{{ route('social.posts.show', $post) }}'" style="cursor:pointer">
                        <td>
                            @if($post->currentVersion?->preview_url)
                                <span class="badge" style="background:var(--bg2);color:var(--text2);border:1px solid var(--line2);font-size:10px;padding:3px 6px;">Media presente</span>
                            @else
                                <span class="badge" style="background:transparent;color:var(--text3);border:1px dashed var(--line2);font-size:10px;padding:3px 6px;">No media</span>
                            @endif
                        </td>
                        <td class="name-col">
                            <div>{{ $post->title }}</div>
                            <div class="social-index-meta">v{{ $post->currentVersion->version_number ?? 1 }}</div>
                        </td>
                        <td>
                            <div>{{ $post->project?->name ?? 'Nessun Progetto' }}</div>
                            <div class="social-index-meta">{{ $post->client?->name ?? 'Nessun Cliente' }}</div>
                        </td>
                        <td>
                            <span class="badge" style="background: {{ $post->status->color() }}; color: #fff; border: none;">{{ $post->status->label() }}</span>
                        </td>
                        <td class="mono-col">
                            {{ $post->updated_at->diffForHumans() }}
                        </td>
                        <td style="text-align: right">
                            <span class="social-action-btn">Dettagli</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="social-empty-state" style="border: none;">
                            Nessun Social Post trovato.
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
