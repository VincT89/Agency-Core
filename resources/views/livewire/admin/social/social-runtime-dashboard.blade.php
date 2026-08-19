<div class="social-runtime-page">
    <x-page-header
        eyebrow="Social media"
        meta="Monitoraggio dei tentativi, degli errori e delle pubblicazioni asincrone."
    >
        <x-slot:title><strong>Controllo pubblicazioni social</strong></x-slot:title>
        <x-slot:actions>
            <button type="button" wire:click="$refresh" class="btn btn-g btn-sm">Aggiorna</button>
        </x-slot:actions>
    </x-page-header>

    <div class="social-runtime-stats">
        @forelse($retryStats as $stat)
            <section class="social-runtime-stat" aria-label="Statistiche {{ $stat->platform->value }}">
                <div class="social-runtime-stat-platform">{{ $stat->platform->value }}</div>
                <div class="social-runtime-stat-metrics">
                    <div>
                        <span>Tentativi</span>
                        <strong>{{ $stat->total_attempts }}</strong>
                    </div>
                    <div>
                        <span>Controlli automatici</span>
                        <strong>{{ $stat->total_polls }}</strong>
                    </div>
                </div>
            </section>
        @empty
            <div class="social-runtime-stats-empty">Nessuna attività social registrata.</div>
        @endforelse
    </div>

    <div class="social-runtime-section">
        <x-panel title="Revisioni manuali ({{ $needsManualReview->count() }})" dot="var(--red)">
            <div class="table-responsive">
                <table class="t-table social-runtime-table social-runtime-table-review">
                    <colgroup>
                        <col><col><col><col>
                    </colgroup>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Piattaforma</th>
                            <th>Esito</th>
                            <th>Avviata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($needsManualReview as $pub)
                            <tr>
                                <td class="mono-col">#{{ $pub->id }}</td>
                                <td class="name-col">{{ $pub->platform->value }}</td>
                                <td class="u-text-red">Richiede un controllo prima di riprovare.</td>
                                <td class="mono-col">{{ $pub->publishing_started_at?->diffForHumans() ?? 'Non disponibile' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="u-empty-state">Nessuna pubblicazione richiede revisione manuale.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    </div>

    <div class="social-runtime-section">
        <x-panel title="Non completate nelle ultime 24 ore ({{ $failedLast24h->count() }})" dot="var(--orange)">
            <div class="table-responsive">
                <table class="t-table social-runtime-table social-runtime-table-failed">
                    <colgroup>
                        <col><col><col><col>
                    </colgroup>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Piattaforma</th>
                            <th>Esito</th>
                            <th>Aggiornata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($failedLast24h as $pub)
                            <tr>
                                <td class="mono-col">#{{ $pub->id }}</td>
                                <td class="name-col">{{ $pub->platform->value }}</td>
                                <td>Pubblicazione non completata. Controlla l'account collegato o riprova.</td>
                                <td class="mono-col">{{ $pub->updated_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="u-empty-state">Nessun fallimento nelle ultime 24 ore.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    </div>

    <div class="social-runtime-section">
        <x-panel title="Pubblicazioni in corso ({{ $pendingAndPublishing->count() }})" dot="var(--blue)">
            <div class="table-responsive">
                <table class="t-table social-runtime-table social-runtime-table-pending">
                    <colgroup>
                        <col><col><col><col><col>
                    </colgroup>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Piattaforma</th>
                            <th>Stato</th>
                            <th>Prossimo controllo</th>
                            <th>Tentativi / controlli</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingAndPublishing as $pub)
                            <tr>
                                <td class="mono-col">#{{ $pub->id }}</td>
                                <td class="name-col">{{ $pub->platform->value }}</td>
                                <td>
                                    <x-badge :status="$pub->status->value" :label="$pub->status->label()" />
                                </td>
                                <td class="mono-col {{ $pub->stale_deadline_at && $pub->stale_deadline_at < now() ? 'u-text-red u-text-strong' : '' }}">
                                    {{ $pub->stale_deadline_at?->diffForHumans() ?? 'Non disponibile' }}
                                </td>
                                <td class="mono-col">
                                    {{ $pub->attempt_count }} / {{ $pub->poll_count }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="u-empty-state">Coda vuota. Nessuna pubblicazione in corso.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    </div>
</div>
