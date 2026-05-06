<div>
    <x-page-header eyebrow="Social">
        <x-slot:title><strong>Progetti Marketing</strong></x-slot:title>
        <x-slot name="actions">
            <a href="{{ route('marketing-campaigns.create') }}" class="btn btn-p" wire:navigate>+ Nuovo Progetto</a>
        </x-slot>
    </x-page-header>

    <div class="filter-bar justify-end">
        <input type="text" class="form-in" placeholder="Cerca progetto..." wire:model.live.debounce.300ms="search" style="padding:5px 10px;font-size:11px;width:250px">
        <select class="form-sel" wire:model.live="clientId" style="padding:5px 10px;font-size:11px;width:160px">
          <option value="">Tutti i Clienti</option>
          @foreach($clients as $client)
            <option value="{{ $client->id }}">{{ $client->name }}</option>
          @endforeach
        </select>
        <select class="form-sel" wire:model.live="status" style="padding:5px 10px;font-size:11px;width:160px">
          <option value="">Tutti gli Stati</option>
          @foreach($statuses as $st)
            <option value="{{ $st->value }}">{{ $st->label() }}</option>
          @endforeach
        </select>
        @if($search || $clientId || $status)
            <button wire:click="$set('search', ''); $set('clientId', ''); $set('status', '')" class="btn btn-g" style="padding:5px 10px;font-size:11px">Reset</button>
        @endif
    </div>

    <x-panel>
    <div class="table-wrap">
      <table class="t-table">
        <thead>
          <tr>
            <th>Progetto</th>
            <th>Cliente</th>
            <th>Stato</th>
            <th>Timeline</th>
            <th class="text-right">Azioni</th>
          </tr>
        </thead>
        <tbody>
          @forelse($campaigns as $camp)
            <tr onclick="window.location='{{ route('marketing-campaigns.show', $camp->id) }}'" style="cursor:pointer">
              <td class="name-col">
                {{ $camp->name }}
                @if($camp->description)
                  <div style="font-size:11px;color:var(--text3);margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:250px;">{{ $camp->description }}</div>
                @endif
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  @if($camp->client->logo_url)
                    <img src="{{ $camp->client->logo_url }}" style="width:24px;height:24px;border-radius:50%;object-fit:cover;border:1px solid var(--line);">
                  @endif
                  <span class="mono-col">{{ $camp->client->name }}</span>
                </div>
              </td>
              <td>
                <x-badge :status="$camp->status->value" :label="$camp->status->label()" />
              </td>
              <td class="mono-col">
                @if($camp->starts_at)
                  {{ $camp->starts_at->format('d/m/Y') }} 
                  @if($camp->ends_at)
                    - {{ $camp->ends_at->format('d/m/Y') }}
                  @endif
                @else
                  -
                @endif
              </td>
              <td>
                <a href="{{ route('marketing-campaigns.show', $camp->id) }}" wire:navigate class="btn-icon" onclick="event.stopPropagation()">→</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" style="text-align:center;color:var(--text3);padding:32px">
                Nessun progetto trovato.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      {{ $campaigns->links() }}
    </div>
    </x-panel>
</div>
