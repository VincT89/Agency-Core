<section id="clients-to-collect" class="finance-receivables u-mb-lg" aria-labelledby="clients-to-collect-title" x-data="{ search: '' }">
    <x-panel padded>
        <div class="finance-receivables-heading">
            <h2 id="clients-to-collect-title">Clienti che devono saldare</h2>
            @if($receivableClients->isNotEmpty())
                <div class="finance-receivables-search">
                    <label class="sr-only" for="receivable-client-search">Cerca nei clienti che devono saldare</label>
                    <input id="receivable-client-search" type="search" class="form-in" placeholder="Cerca cliente" x-model.debounce.150ms="search" @keydown.escape="search = ''" maxlength="100" aria-controls="receivable-client-list">
                </div>
            @endif
        </div>
        @if($receivableClients->isEmpty())
            <p class="u-text-meta">Nessun cliente con fatture da saldare{{ collect($filters)->only(['from', 'to', 'client_id', 'project_id', 'q', 'invoice_status', 'min_amount', 'max_amount'])->filter(fn ($value) => filled($value))->isNotEmpty() ? ' con questi filtri' : '' }}.</p>
        @else
            <ul id="receivable-client-list" class="finance-receivables-list" x-ref="clients">
                @foreach($receivableClients as $client)
                    @php
                        $invoiceFilters = array_filter([
                            'client_id' => $client->client_id, 'project_id' => $filters['project_id'] ?? null,
                            'status' => $filters['invoice_status'] ?? 'pending', 'start_date' => $from, 'end_date' => $to,
                            'document_number' => $filters['q'] ?? null, 'min_amount' => $filters['min_amount'] ?? null, 'max_amount' => $filters['max_amount'] ?? null,
                        ], fn ($value) => filled($value));
                    @endphp
                    <li data-client-name="{{ mb_strtolower($client->client_name) }}" x-show="!search.trim() || $el.dataset.clientName.includes(search.trim().toLocaleLowerCase('it'))">
                        <a class="finance-receivable-link {{ $client->overdue_invoices > 0 ? 'is-overdue' : '' }}" href="{{ route('invoices.index', $invoiceFilters) }}" aria-label="Fatture da incassare di {{ $client->client_name }}">
                            <span class="finance-receivable-name">{{ $client->client_name }}</span>
                            <strong class="finance-receivable-amount">{{ $money($client->outstanding) }}</strong>
                            <span class="finance-receivable-footer"><span class="finance-receivable-status">{{ $client->open_invoices }} {{ $client->open_invoices == 1 ? 'fattura aperta' : 'fatture aperte' }}@if($client->overdue_invoices > 0)<span class="u-text-red">{{ $client->overdue_invoices }} {{ $client->overdue_invoices == 1 ? 'scaduta' : 'scadute' }}</span>@endif</span><span class="finance-receivable-action">Vedi fatture</span></span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <p class="u-text-meta" role="status" x-cloak x-show="search.trim() && ![...$refs.clients.children].some(item => item.dataset.clientName.includes(search.trim().toLocaleLowerCase('it')))">Nessun cliente trovato.</p>
        @endif
    </x-panel>
</section>
