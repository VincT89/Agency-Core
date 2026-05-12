<div>
    <x-page-header eyebrow="Social">
        <x-slot:title><strong>Progetti Marketing</strong></x-slot:title>
        <x-slot name="actions">
            <a href="{{ route('marketing-campaigns.create') }}" class="btn btn-p" wire:navigate>+ Nuovo Progetto</a>
        </x-slot>
    </x-page-header>

    <div class="filter-bar justify-end">
        <input type="text" class="form-in mkt-search-in" placeholder="Cerca progetto..." wire:model.live.debounce.300ms="search">
        <select class="form-sel mkt-filter-sel" wire:model.live="clientId">
          <option value="">Tutti i Clienti</option>
          @foreach($clients as $client)
            <option value="{{ $client->id }}">{{ $client->name }}</option>
          @endforeach
        </select>
        <select class="form-sel mkt-filter-sel" wire:model.live="status">
          <option value="">Tutti gli Stati</option>
          @foreach($statuses as $st)
            <option value="{{ $st->value }}">{{ $st->label() }}</option>
          @endforeach
        </select>
        @if($search || $clientId || $status)
            <button wire:click="$set('search', ''); $set('clientId', ''); $set('status', '')" class="btn btn-g mkt-reset-btn">Reset</button>
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
            <tr x-data @click="window.Livewire.navigate('{{ route('marketing-campaigns.show', $camp->id) }}')" class="mkt-table-row u-cursor-pointer hover-bg">
              <td class="name-col">
                {{ $camp->name }}
                @if($camp->description)
                  <div class="mkt-table-desc">{{ $camp->description }}</div>
                @endif
              </td>
              <td>
                <div class="mkt-flex-center-gap8">
                  @if($camp->client->logo_url)
                    <img src="{{ $camp->client->logo_url }}" class="mkt-table-avatar">
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
                <a href="{{ route('marketing-campaigns.show', $camp->id) }}" wire:navigate class="btn-icon" @click.stop>→</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="mkt-table-empty">
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
