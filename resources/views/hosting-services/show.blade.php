<x-app-layout title="{{ $hostingService->name }}">
    <div class="u-mb-lg u-flex-end">
        <a href="{{ route('hosting-services.index') }}" wire:navigate class="btn btn-g u-flex-center u-gap-xs">
            <i data-lucide="arrow-left" class="u-icon-sm"></i> Torna alla lista
        </a>
    </div>
    <x-page-header eyebrow="Servizio · {{ ucfirst($hostingService->type) }}">
        <x-slot:title><strong>{{ $hostingService->name }}</strong></x-slot:title>
        <x-slot:actions>
            <a href="{{ $hostingService->type === 'domain' ? route('hosting-services.index', ['type' => 'domain']) : route('hosting-services.index', ['exclude_type' => 'domain']) }}" class="btn btn-g">← Indietro</a>
            <x-badge :status="$hostingService->status === 'active' ? 'success' : 'danger'" :label="$hostingService->status === 'active' ? 'Attivo' : ucfirst($hostingService->status)" />
            <a href="{{ route('hosting-services.edit', $hostingService) }}" class="btn btn-g">Modifica</a>
            
            <x-delete-modal 
                action="{{ route('hosting-services.destroy', $hostingService) }}" 
                title="Elimina Servizio" 
                message="Eliminare definitivamente il servizio '{{ $hostingService->name }}'?"
                confirmText="{{ $hostingService->name }}">
                <button type="button" class="btn btn-g hosting-past-due hosting-services-border-line">
                    Elimina
                </button>
            </x-delete-modal>
        </x-slot:actions>
    </x-page-header>

    <div class="g-2col u-mb-lg">
        {{-- Colonna Sinistra: Dettagli --}}
        <x-panel title="Dettagli Servizio" dot="var(--teal)" padded>
            <div class="hosting-detail-row">
                <div class="form-lbl">Cliente</div>
                <div class="hosting-notes-text hosting-detail-val">
                    @if($hostingService->client)
                        <a href="{{ route('clients.show', $hostingService->client) }}" class="hosting-client-link">{{ $hostingService->client->name }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="hosting-detail-row">
                <div class="form-lbl">Dominio / URL</div>
                <div class="hosting-notes-text hosting-detail-val">
                    @if($hostingService->domain)
                        <a href="{{ str_starts_with($hostingService->domain, 'http') ? $hostingService->domain : 'https://' . $hostingService->domain }}" target="_blank" rel="noopener">{{ $hostingService->domain }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="hosting-detail-row">
                <div class="form-lbl">Provider</div>
                <div class="hosting-notes-text hosting-detail-val">{{ $hostingService->provider ?: '—' }}</div>
            </div>
            <div class="hosting-detail-row">
                <div class="form-lbl">Data Rinnovo / Costo</div>
                <div class="hosting-user-val hosting-detail-val">
                    @if($hostingService->renewal_date)
                        <span class="{{ $hostingService->renewal_date->isPast() ? 'hosting-past-due' : '' }}">
                            {{ $hostingService->renewal_date->format('d/m/Y') }}
                        </span>
                    @else
                        —
                    @endif
                    <br>
                    {{ $hostingService->renewal_cost ? '€ ' . number_format($hostingService->renewal_cost, 2, ',', '.') : '—' }}
                </div>
            </div>
            <div class="hosting-detail-row">
                <div class="form-lbl">Credenziali Accesso</div>
                <div class="hosting-user-val hosting-detail-val">
                    User: {{ $hostingService->username ?: '—' }}<br>
                    Pass: 
                    @if($hostingService->password)
                        <div class="hosting-password-container" data-id="{{ $hostingService->id }}">
                            <span class="hosting-password-value" data-hidden="true">••••••••</span>
                            <button type="button" class="hosting-password-toggle" title="Mostra/Nascondi"><i data-lucide="eye" class="u-icon-sm"></i></button>
                            <button type="button" class="hosting-password-copy" title="Copia"><i data-lucide="copy" class="u-icon-sm"></i></button>
                        </div>
                    @else
                        —
                    @endif
                </div>
            </div>

            @if($hostingService->notes)
            <div class="hosting-detail-row">
                <div class="form-lbl">Note</div>
                <div class="hosting-notes-text hosting-detail-val">{{ $hostingService->notes }}</div>
            </div>
            @endif
        </x-panel>

        {{-- Spese Associate --}}
        <div class="u-mt-lg">
            <x-panel title="Spese Associate" dot="var(--orange)">
                @if($hostingService->expenses->isEmpty())
                    <div class="hosting-empty-p">
                        <x-empty-state message="Nessuna spesa registrata per questo servizio." icon="receipt" />
                    </div>
                @else
                    <table class="t-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Spesa</th>
                                <th>Importo</th>
                                <th>Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($hostingService->expenses()->orderBy('expense_date', 'desc')->get() as $expense)
                            <tr x-data @click="window.Livewire.navigate('{{ route('expenses.show', $expense) }}')" class="hosting-row-link hover-bg u-cursor-pointer">
                                <td class="mono-col">{{ $expense->expense_date->format('d/m/Y') }}</td>
                                <td>{{ $expense->title }}</td>
                                <td class="mono-col">&euro; {{ number_format($expense->amount, 2, ',', '.') }}</td>
                                <td>
                                    @if($expense->status === 'paid')
                                        <span class="badge badge-success">Pagata</span>
                                    @elseif($expense->status === 'cancelled')
                                        <span class="badge badge-gray">Annullata</span>
                                    @elseif($expense->is_overdue)
                                        <span class="badge badge-danger">Scaduta</span>
                                    @else
                                        <span class="badge badge-warning">Da Pagare</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-panel>
        </div>

        {{-- Allegati --}}
        <div class="u-mt-lg">
            <x-panel title="Documenti e Credenziali" dot="var(--blue)" padded>
                <livewire:shared.attachment-manager :model="$hostingService" />
            </x-panel>
        </div>

        {{-- Colonna Destra: Storico Interventi --}}
        <div>
            <x-panel title="Registra Intervento" dot="var(--accent)" padded class="u-mb-lg">
                <form method="POST" action="{{ route('hosting-services.interventions.store', $hostingService) }}">
                    @csrf
                    <div class="form-row full">
                        <x-form-group name="title">
                            <input name="title" class="form-in" placeholder="Titolo (es. Aggiornamento plugin)" required>
                        </x-form-group>
                    </div>
                    <div class="form-row">
                        <x-form-group name="intervention_date">
                            <input type="date" name="intervention_date" class="form-in" value="{{ now()->format('Y-m-d') }}" required>
                        </x-form-group>
                        <x-form-group name="cost">
                            <input type="number" step="0.01" name="cost" class="form-in" placeholder="Costo € (opzionale)">
                        </x-form-group>
                    </div>
                    <div class="form-row full">
                        <x-form-group name="description">
                            <textarea name="description" class="form-ta" rows="2" placeholder="Descrizione dettagliata (opzionale)..."></textarea>
                        </x-form-group>
                    </div>
                    <div class="hosting-btn-right">
                        <button type="submit" class="btn btn-p hosting-btn-sm">Salva Intervento</button>
                    </div>
                </form>
            </x-panel>

            <x-panel title="Storico Interventi">
                @if($hostingService->interventions->isEmpty())
                    <div class="hosting-empty-p">
                        <x-empty-state message="Nessun intervento registrato." icon="tool" />
                    </div>
                @else
                    <table class="t-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Intervento</th>
                                <th>Operatore</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($hostingService->interventions()->latest('intervention_date')->get() as $intervention)
                            <tr>
                                <td class="mono-col">{{ $intervention->intervention_date->format('d/m/Y') }}</td>
                                <td>
                                    <div class="hosting-intervention-title">{{ $intervention->title }}</div>
                                    @if($intervention->description)
                                        <div class="hosting-desc-truncate" title="{{ $intervention->description }}">
                                            {{ $intervention->description }}
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $intervention->user->name ?? '—' }}</td>
                                <td>
                                    <form action="{{ route('hosting-services.interventions.destroy', [$hostingService, $intervention]) }}" method="POST" class="js-confirm-delete">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icon hosting-past-due">✕</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-panel>
        </div>
    </div>
</x-app-layout>
