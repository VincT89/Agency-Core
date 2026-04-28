<div>
    <x-page-header eyebrow="Social">
        <x-slot:title><strong>Progetti Marketing</strong></x-slot:title>
        <x-slot name="actions">
            <a href="{{ route('marketing-projects.create') }}" wire:navigate class="btn btn-p">+ Nuovo Progetto</a>
        </x-slot>
    </x-page-header>

    <div class="filters-row">
        <input type="text" wire:model.live="search" class="form-in" placeholder="Cerca per titolo o cliente...">
        <select wire:model.live="filterStatus" class="form-in">
            <option value="">Tutti gli stati</option>
            @foreach(\App\Enums\Social\MarketingProjectStatus::cases() as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </select>
        @if($search || $filterStatus)
            <button wire:click="$set('search', ''); $set('filterStatus', '')" class="btn btn-g">Reset</button>
        @endif
    </div>

    <x-panel>
        <table class="t-table">
            <thead>
                <tr>
                    <th>Titolo</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Stato</th>
                    <th>Creazione</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                    <tr onclick="window.location='{{ route('marketing-projects.show', $project->id) }}'" style="cursor:pointer">
                        <td class="name-col">{{ $project->title }}</td>
                        <td>{{ $project->client->name ?? '-' }}</td>
                        <td>
                            <span class="badge bd">{{ $project->type->label() }}</span>
                        </td>
                        <td>
                            <x-badge :status="$project->status->value" :label="$project->status->label()" />
                        </td>
                        <td class="mono-col">{{ $project->created_at->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('marketing-projects.show', $project->id) }}" wire:navigate class="btn-icon" onclick="event.stopPropagation()">→</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;color:var(--text3);padding:32px">
                            Nessun progetto trovato.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($projects->hasPages())
            <div style="padding: 12px 20px;">
                {{ $projects->links() }}
            </div>
        @endif
    </x-panel>
</div>
