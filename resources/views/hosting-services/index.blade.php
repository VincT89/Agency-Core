<x-app-layout title="{{ request('type') === 'domain' ? 'Domini' : 'Hosting' }}">
    <x-page-header eyebrow="Servizi IT" :meta="$services->total() . (request('type') === 'domain' ? ' domini trovati' : ' servizi trovati')">
        <x-slot:title><strong>{{ request('type') === 'domain' ? 'Domini' : 'Hosting' }}</strong></x-slot:title>
        <x-slot:actions>
            <a href="{{ route('hosting-services.create', ['type' => request('type'), 'exclude_type' => request('exclude_type')]) }}" class="btn btn-p">
                + Aggiungi {{ request('type') === 'domain' ? 'Dominio' : 'Servizio' }}
            </a>
        </x-slot:actions>
    </x-page-header>

    @php
        $domainContext = request('type') === 'domain';
        $excludeDomains = request('exclude_type') === 'domain';
    @endphp

    <div class="filter-bar">
        <div class="pills u-m-0">
            <a href="{{ request()->fullUrlWithQuery(['status_filter' => null]) }}" class="pill {{ !request('status_filter') ? 'on' : '' }}">Tutti</a>
            <a href="{{ request()->fullUrlWithQuery(['status_filter' => 'expiring']) }}" class="pill {{ request('status_filter') === 'expiring' ? 'on' : '' }}">In Scadenza</a>
            <a href="{{ request()->fullUrlWithQuery(['status_filter' => 'expired']) }}" class="pill {{ request('status_filter') === 'expired' ? 'on' : '' }}">Scaduti</a>
            <a href="{{ request()->fullUrlWithQuery(['status_filter' => 'active']) }}" class="pill {{ request('status_filter') === 'active' ? 'on' : '' }}">Attivi</a>
        </div>

        <form method="GET" action="{{ route('hosting-services.index') }}" class="hosting-filters-panel u-ml-auto" x-data>
            @if(request('status_filter'))
                <input type="hidden" name="status_filter" value="{{ request('status_filter') }}">
            @endif
            @if($domainContext)
                <input type="hidden" name="type" value="domain">
            @else
                <label for="hosting-type-filter" class="sr-only">Tipo di servizio</label>
                <select id="hosting-type-filter" name="type" class="form-sel hosting-input-sm hosting-w-150" @change="$el.form.submit()">
                    <option value="">Tutti i tipi</option>
                    @unless($excludeDomains)
                        <option value="domain" {{ request('type') === 'domain' ? 'selected' : '' }}>Domini</option>
                    @endunless
                    <option value="hosting" {{ request('type') === 'hosting' ? 'selected' : '' }}>Hosting</option>
                    <option value="website" {{ request('type') === 'website' ? 'selected' : '' }}>Website</option>
                    <option value="maintenance" {{ request('type') === 'maintenance' ? 'selected' : '' }}>Manutenzioni</option>
                    <option value="email" {{ request('type') === 'email' ? 'selected' : '' }}>Email</option>
                    <option value="dns" {{ request('type') === 'dns' ? 'selected' : '' }}>DNS</option>
                    <option value="other" {{ request('type') === 'other' ? 'selected' : '' }}>Altro</option>
                </select>
            @endif
            @if($excludeDomains)
                <input type="hidden" name="exclude_type" value="domain">
            @endif
            <label for="hosting-search" class="sr-only">Cerca servizi</label>
            <input id="hosting-search" type="search" name="search" value="{{ request('search') }}"
                   class="form-in hosting-input-sm hosting-w-250" 
                   placeholder="Nome, provider, cliente o dominio"
                   @input.debounce.500ms="$el.form.submit()">
            @if(request('search'))
                <a href="{{ route('hosting-services.index', ['type' => request('type'), 'exclude_type' => request('exclude_type')]) }}" class="btn btn-g hosting-input-sm">Reset</a>
            @endif
        </form>
    </div>

    <x-panel>
        <table class="t-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Dominio</th>
                    <th>Credenziali</th>
                    <th>Scadenza</th>
                    <th class="t-actions">Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($services as $service)
                <tr class="hosting-row-link js-row-link" data-href="{{ route('hosting-services.show', $service) }}">
                    <td class="name-col">
                        {{ $service->name }}
                        @if($service->provider)
                            <div class="hosting-provider-sub">{{ $service->provider }}</div>
                        @endif
                    </td>
                    <td>
                        @if($service->client)
                            <a href="{{ route('clients.show', $service->client) }}" class="hosting-client-link">{{ $service->client->name }}</a>
                        @else
                            <span class="hosting-text-na">-</span>
                        @endif
                    </td>
                    <td>
                        <span class="hosting-type-badge">
                            {{ ucfirst($service->type) }}
                        </span>
                    </td>
                    <td class="mono-col">
                        @if($service->domain)
                            {{ $service->domain }}
                        @else
                            <span class="hosting-text-na">-</span>
                        @endif
                    </td>
                    <td>
                        @if($service->username || $service->password)
                            @can('manageCredentials', $service)
                                @if($service->username)
                                    <div class="hosting-user-lbl">User: <span class="hosting-user-val">{{ $service->username }}</span></div>
                                @endif
                                @if($service->password)
                                    <div class="hosting-user-lbl">Pass: <span class="hosting-password-value">••••••••</span></div>
                                @endif
                            @else
                                <span class="hosting-text-na">Riservate</span>
                            @endcan
                        @else
                            <span class="hosting-text-na">N/A</span>
                        @endif
                    </td>
                    <td class="mono-col">
                        @if($service->renewal_date)
                            @if($service->is_expired)
                                <span class="badge badge-danger" title="{{ $service->renewal_date->format('d/m/Y') }}">
                                    Scaduto ({{ $service->renewal_date->format('d/m/Y') }})
                                </span>
                            @elseif($service->is_expiring_soon)
                                <span class="badge badge-warning" title="{{ $service->renewal_date->format('d/m/Y') }}">
                                    Scade tra {{ $service->days_until_renewal }} gg
                                </span>
                            @else
                                <span class="badge badge-gray">
                                    {{ $service->renewal_date->format('d/m/Y') }}
                                </span>
                            @endif
                        @else
                            <span class="hosting-text-na">-</span>
                        @endif
                    </td>
                    <td class="t-actions">
                        <a href="{{ route('hosting-services.edit', $service) }}" class="btn-icon js-stop-propagation" title="Modifica">✎</a>
                    </td>
                </tr>
                @empty
                <tr class="hosting-empty-row"><td colspan="7">Nessun servizio trovato</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($services->hasPages())
            <div class="hosting-services-pagination">
                {{ $services->links() }}
            </div>
        @endif
    </x-panel>
</x-app-layout>
