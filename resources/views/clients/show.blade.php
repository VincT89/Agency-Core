<x-app-layout title="{{ $client->name }}">
    <div class="u-mb-lg u-flex-end">
        <a href="{{ route('clients.index') }}" wire:navigate class="btn btn-g u-flex-center u-gap-xs">
            <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna alla lista
        </a>
    </div>
    <x-page-header
        eyebrow="Dettaglio · Cliente"
        
    >
    <x-slot:title><strong>{{ $client->name }}</strong></x-slot:title>
        <x-slot:actions>
            <x-badge :status="$client->status" :label="$client->status_label" />
            @can('update', $client)
                <a href="{{ route('clients.edit', $client) }}" class="btn btn-g">Modifica</a>
            @endcan
        
            @can('delete', $client)
                <x-delete-modal 
                    action="{{ route('clients.destroy', $client) }}" 
                    title="Elimina Cliente" 
                    message="Eliminare definitivamente il cliente '{{ $client->name }}'? L'operazione rimuoverà anche i dati collegati e non è reversibile."
                    confirmText="{{ $client->name }}">
                    <button type="button" class="btn btn-g" style="color:var(--red);border-color:rgba(245,75,75,.3)">
                        Elimina
                    </button>
                </x-delete-modal>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="g-2col" style="margin-bottom:20px;">
        <x-panel title="Info Base" dot="var(--teal)" padded>
            <div class="u-flex u-flex-col u-gap-md">
                
                {{-- Header with Logo and Name --}}
                <div class="u-flex u-items-center u-gap-md u-pb-md" style="border-bottom: 1px solid var(--line);">
                    <div style="width: 80px; height: 80px; border-radius: 50%; border: 1px solid var(--line); overflow: hidden; display: flex; align-items: center; justify-content: center; background: #fff; flex-shrink: 0;">
                        @if($client->logo_url)
                            <img src="{{ $client->logo_url }}" alt="Logo {{ $client->name }}" style="width: 100%; height: 100%; object-fit: cover; object-position: center;">
                        @else
                            <i data-lucide="building" class="u-icon-lg" style="color: var(--text3)"></i>
                        @endif
                    </div>
                    <div>
                        <div class="u-text-strong u-text-lg">{{ $client->name }}</div>
                        <div class="u-text-meta">{{ $client->company_name ?? 'Nessuna ragione sociale specificata' }}</div>
                    </div>
                </div>

                {{-- Activity Description --}}
                <div class="form-g mb-0">
                    <div class="form-lbl">Descrizione attività cliente</div>
                    <div style="color: var(--text); font-family: var(--sans); font-size: 14px;">
                        {{ $client->activity_description ?: '—' }}
                    </div>
                </div>

                {{-- 2-Col Grid for Details --}}
                <div class="g-2col">
                    <div class="form-g mb-0">
                        <div class="form-lbl">Partita IVA / C.F.</div>
                        <div style="color: var(--text); font-family: var(--mono); font-size: 14px;">{{ $client->vat_number ?? '—' }}</div>
                    </div>
                    <div class="form-g mb-0">
                        <div class="form-lbl">Referente</div>
                        <div style="color: var(--text); font-family: var(--sans); font-size: 14px;">{{ $client->reference_person ?? '—' }}</div>
                    </div>
                    <div class="form-g mb-0">
                        <div class="form-lbl">Email / Telefono</div>
                        <div style="color: var(--text); font-family: var(--sans); font-size: 14px;">
                            {{ $client->email ?? '—' }} <br> 
                            {{ $client->phone ?? '—' }}
                        </div>
                    </div>
                    <div class="form-g mb-0">
                        <div class="form-lbl">Fatturazione (PEC / SDI)</div>
                        <div style="color: var(--text); font-family: var(--mono); font-size: 14px;">
                            PEC: {{ $client->pec ?? '—' }} <br>
                            SDI: {{ $client->sdi_code ?? '—' }} <br>
                            Email: {{ $client->billing_email ?? '—' }}
                        </div>
                    </div>
                </div>

                {{-- Full width details --}}
                <div class="form-g mb-0">
                    <div class="form-lbl">Sede</div>
                    <div style="color: var(--text); font-family: var(--sans); font-size: 14px;">
                        {{ $client->address ?? '—' }}<br>
                        {{ trim(implode(' ', array_filter([$client->postal_code, $client->city, $client->province ? "({$client->province})" : null, $client->country]))) }}
                    </div>
                </div>
                
                <div class="form-g mb-0">
                    <div class="form-lbl">Registrato il</div>
                    <div style="color: var(--text); font-family: var(--mono); font-size: 14px;">{{ $client->created_at->isoFormat('D MMMM YYYY') }}</div>
                </div>

            </div>
        </x-panel>
        <div>
            <livewire:client.client-social-overview :client="$client" />

            <x-panel title="Ticket Recenti" dot="var(--accent)" padded>
                @forelse($client->tickets as $t)
                    <div style="padding:8px 0;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;">
                        <a href="{{ route('tickets.show', $t) }}" style="color:var(--text);text-decoration:none">{{ $t->title }}</a>
                        <x-badge :status="$t->status" :label="$t->status_label" />
                    </div>
                @empty
                    <div style="padding:16px;">
                        <x-empty-state message="Nessun ticket recente per questo cliente." icon="ticket" />
                    </div>
                @endforelse
            </x-panel>
        </div>
    </div>

    <div style="margin-bottom:20px;">
        <livewire:client.client-social-account-form :client="$client" />
    </div>

    <x-panel title="Commesse Attive ({{ $client->projects->count() }})">
        @if($client->projects->isEmpty())
            <div style="padding:16px;">
                <x-empty-state message="Nessuna commessa registrata per questo cliente." icon="folder-open" />
            </div>
        @else
            <table class="t-table">
                <thead>
                    <tr>
                        <th>Nome Commessa</th>
                        <th>Stato</th>
                        <th>Data avvio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($client->projects as $p)
                    <tr onclick="window.location='{{ route('projects.show', $p) }}'" style="cursor:pointer">
                        <td class="name-col">{{ $p->name }}</td>
                        <td><x-badge :status="$p->status" :label="$p->status_label" /></td>
                        <td class="mono-col">{{ $p->created_at->format('d/m/Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-panel>


    <x-audit-timeline :logs="$client->auditLogs" />
    
    {{-- Allegati --}}
    <x-attachments-panel :model="$client" />
</x-app-layout>