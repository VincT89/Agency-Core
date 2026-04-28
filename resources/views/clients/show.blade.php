<x-app-layout title="{{ $client->name }}">
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
            <div class="form-g mb-2">
                <div class="form-lbl">Partita IVA / C.F.</div>
                <div style="color:var(--text);font-family:var(--mono)">{{ $client->vat_number ?? '—' }}</div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Email / Telefono</div>
                <div style="color:var(--text);font-family:var(--sans)">{{ $client->email ?? '—' }} <br> {{ $client->phone ?? '—' }}</div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Fatturazione (PEC / SDI)</div>
                <div style="color:var(--text);font-family:var(--mono)">
                    PEC: {{ $client->pec ?? '—' }} <br>
                    SDI: {{ $client->sdi_code ?? '—' }} <br>
                    Email: {{ $client->billing_email ?? '—' }}
                </div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Referente</div>
                <div style="color:var(--text);font-family:var(--sans)">{{ $client->reference_person ?? '—' }}</div>
            </div>
            <div class="form-g mb-2">
                <div class="form-lbl">Sede</div>
                <div style="color:var(--text);font-family:var(--sans)">
                    {{ $client->address ?? '—' }}<br>
                    {{ trim(implode(' ', array_filter([$client->postal_code, $client->city, $client->province ? "({$client->province})" : null, $client->country]))) }}
                </div>
            </div>
            <div class="form-g">
                <div class="form-lbl">Registrato il</div>
                <div style="color:var(--text);font-family:var(--mono)">{{ $client->created_at->isoFormat('D MMMM YYYY') }}</div>
            </div>
        </x-panel>
        <div>
            <x-panel title="Stato Onboarding Social" dot="var(--orange)" padded style="margin-bottom:20px;">
                @php
                    $isMetaReady = $client->isMetaReady();
                    $tiktokAccount = $client->socialAccountFor(\App\Enums\Social\SocialPlatform::Tiktok->value);
                    $isTiktokReady = $tiktokAccount?->isReadyToPublish() ?? false;
                @endphp
                
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--line);">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <i data-lucide="facebook" style="width:16px; height:16px; color:var(--text3);"></i>
                        <span style="font-family:var(--sans); font-size:13px; color:var(--text);">Meta (Facebook / Instagram)</span>
                    </div>
                    @if($isMetaReady)
                        <span style="font-family:var(--mono); font-size:10px; padding:2px 6px; border-radius:4px; background:var(--green)15; color:var(--green); border:1px solid var(--green)30;">
                            META PRONTO
                        </span>
                    @else
                        <span style="font-family:var(--mono); font-size:10px; padding:2px 6px; border-radius:4px; background:var(--orange)15; color:var(--orange); border:1px solid var(--orange)30;">
                            META INCOMPLETO
                        </span>
                    @endif
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--line);">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text3);">
                            <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
                        </svg>
                        <span style="font-family:var(--sans); font-size:13px; color:var(--text);">TikTok (Opzionale)</span>
                    </div>
                    @if($isTiktokReady)
                        <span style="font-family:var(--mono); font-size:10px; padding:2px 6px; border-radius:4px; background:var(--green)15; color:var(--green); border:1px solid var(--green)30;">
                            TIKTOK PRONTO
                        </span>
                    @else
                        <span style="font-family:var(--mono); font-size:10px; padding:2px 6px; border-radius:4px; background:var(--text3)15; color:var(--text3); border:1px solid var(--text3)30;">
                            NON CONFIGURATO
                        </span>
                    @endif
                </div>
            </x-panel>

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

    <x-panel title="Progetti Attivi ({{ $client->projects->count() }})">
        @if($client->projects->isEmpty())
            <div style="padding:16px;">
                <x-empty-state message="Nessun progetto registrato per questo cliente." icon="folder-open" />
            </div>
        @else
            <table class="t-table">
                <thead>
                    <tr>
                        <th>Nome Progetto</th>
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