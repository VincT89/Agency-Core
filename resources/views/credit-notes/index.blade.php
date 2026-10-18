<x-app-layout title="Note di credito">
    <x-page-header><x-slot:title>Note di credito</x-slot:title><x-slot:actions><a href="{{ route('invoices.index') }}" class="btn btn-g">Fatture</a><a href="{{ route('invoices.import') }}" class="btn btn-g">Importa documento</a><a href="{{ route('invoices.import.archive') }}" class="btn btn-p">Importa ZIP Aruba</a></x-slot:actions></x-page-header>
    <p class="u-mb-md">Documenti TD04 separati dalle fatture. Collega ogni nota a una o più fatture dello stesso cliente e indica l’importo da stornare.</p>
    <form method="GET" class="inv-search-form u-mb-md" role="search" aria-label="Cerca note di credito">
        <div class="inv-search-field"><label for="credit-search" class="form-lbl">Numero o cliente</label><input id="credit-search" type="search" name="search" value="{{ request('search') }}" class="form-in" maxlength="100"></div>
        <div class="inv-search-actions"><button class="btn btn-p">Cerca</button>@if(request('search'))<a class="btn btn-g" href="{{ route('credit-notes.index') }}">Azzera filtri</a>@endif</div>
    </form>
    <x-panel>
        <div class="t-table-wrap" role="region" aria-label="Elenco note di credito" tabindex="0"><table class="t-table"><thead><tr><th>Numero originale</th><th>Tipo</th><th>Data</th><th>Cliente</th><th>Totale</th><th>Netto da collegare</th></tr></thead><tbody>
        @forelse($notes as $note)<tr><td><a href="{{ route('credit-notes.show', $note) }}">{{ $note->number }}</a></td><td>TD04</td><td>{{ $note->issue_date->format('d/m/Y') }}</td><td>{{ $note->client->company_name ?: $note->client->name }}</td><td>{{ number_format($note->total, 2, ',', '.') }} €</td><td>{{ number_format($note->unallocated_total, 2, ',', '.') }} €</td></tr>
        @empty<tr><td colspan="6">Nessuna nota di credito trovata.</td></tr>@endforelse
        </tbody></table></div>
        {{ $notes->links() }}
    </x-panel>
</x-app-layout>
