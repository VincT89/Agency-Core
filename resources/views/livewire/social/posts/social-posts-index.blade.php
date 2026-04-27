<div>
    <x-page-header
        eyebrow="Modulo · Social"
        :meta="$posts->total() . ' totali'"
    >
        <x-slot:title><strong>Archivio Social Post</strong></x-slot:title>
        <x-slot:actions>
            <select wire:model.live="statusFilter" class="form-in" style="width: 200px; padding: 6px 12px; font-size: 12px; background-color: var(--bg); border: 1px solid var(--line2); border-radius: var(--r); color: var(--text);">
                <option value="">Tutti gli stati</option>
                @foreach(\App\Enums\Social\SocialPostStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </x-slot:actions>
    </x-page-header>

    <x-panel>
        <table class="t-table">
            <thead>
                <tr>
                    <th style="width: 60px;">Preview</th>
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
                            @if($post->currentVersion && $post->currentVersion->image_path)
                                <img src="{{ Storage::url($post->currentVersion->image_path) }}" alt="Preview" class="social-index-preview">
                            @else
                                <div class="social-index-preview-empty">
                                    <i data-lucide="image" style="width:16px;height:16px;opacity:0.5;"></i>
                                </div>
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
