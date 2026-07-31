<div>
    <div class="mb-6 flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">Controllo pubblicazioni social</h2>
        <button wire:click="$refresh" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Aggiorna</button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        @foreach($retryStats as $stat)
            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-indigo-500">
                <h3 class="text-lg font-semibold text-gray-700 uppercase">{{ $stat->platform->value }}</h3>
                <p class="text-sm text-gray-500 mt-1">Tentativi: <span class="font-medium text-gray-800">{{ $stat->total_attempts }}</span></p>
                <p class="text-sm text-gray-500">Controlli automatici: <span class="font-medium text-gray-800">{{ $stat->total_polls }}</span></p>
            </div>
        @endforeach
    </div>

    <!-- Needs Manual Review -->
    <div class="mb-8">
        <h3 class="text-xl font-semibold text-red-600 mb-3 flex items-center">
            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            Revisioni manuali ({{ $needsManualReview->count() }})
        </h3>
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Piattaforma</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Esito</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avviata</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($needsManualReview as $pub)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#{{ $pub->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $pub->platform->value }}</td>
                            <td class="px-6 py-4 text-sm text-red-500 break-words max-w-xs">Richiede un controllo prima di riprovare.</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $pub->publishing_started_at?->diffForHumans() ?? 'Non disponibile' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">Nessuna pubblicazione richiede revisione manuale.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Failed Last 24h -->
    <div class="mb-8">
        <h3 class="text-xl font-semibold text-orange-600 mb-3">Non completate nelle ultime 24 ore ({{ $failedLast24h->count() }})</h3>
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Piattaforma</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Esito</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aggiornata</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($failedLast24h as $pub)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#{{ $pub->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $pub->platform->value }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">Pubblicazione non completata. Controlla l'account collegato o riprova.</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $pub->updated_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">Nessun fallimento nelle ultime 24 ore.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pending / Publishing -->
    <div>
        <h3 class="text-xl font-semibold text-blue-600 mb-3">Pubblicazioni in corso ({{ $pendingAndPublishing->count() }})</h3>
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Piattaforma</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stato</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prossimo controllo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tentativi / controlli</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($pendingAndPublishing as $pub)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#{{ $pub->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $pub->platform->value }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    {{ $pub->status->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm {{ $pub->stale_deadline_at && $pub->stale_deadline_at < now() ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                                {{ $pub->stale_deadline_at?->diffForHumans() ?? 'Non disponibile' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $pub->attempt_count }} / {{ $pub->poll_count }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Coda vuota. Nessuna pubblicazione in corso.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
