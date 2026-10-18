<x-app-layout title="Modifica scadenza fattura {{ $invoice->display_number }}">
    <div class="invoice-detail invoice-status-page">
        <div class="page-back-row"><a href="{{ route('invoices.show', $invoice) }}" class="btn btn-g">Torna alla fattura</a></div>
        <x-page-header>
            <x-slot:title>Modifica scadenza</x-slot:title>
            <x-slot:meta>Fattura {{ $invoice->display_number }} · {{ $invoice->client?->company_name ?: $invoice->client?->name }}</x-slot:meta>
        </x-page-header>
        <x-panel padded>
            <dl class="invoice-status-summary">
                <div><dt>Data fattura</dt><dd>{{ $invoice->issue_date->format('d/m/Y') }}</dd></div>
                <div><dt>Scadenza attuale nel gestionale</dt><dd>{{ $invoice->due_date?->format('d/m/Y') ?? 'Non indicata' }}</dd></div>
            </dl>
            <p class="invoice-status-help">Questa data viene usata per le scadenze e la previsione degli incassi nel gestionale. Il documento originale e le scadenze riportate nell’XML restano invariati. Una nuova importazione conserva la scadenza gestionale modificata.</p>
            @if($errors->any())<div class="invoice-status-errors" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
            <form method="POST" action="{{ route('invoices.due-date.update', $invoice) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="version" value="{{ $version }}">
                <x-form-group label="Nuova scadenza gestionale" name="due_date" for="invoice-due-date" required>
                    <input id="invoice-due-date" type="date" name="due_date" class="form-in" min="{{ $invoice->issue_date->format('Y-m-d') }}" value="{{ old('due_date', $invoice->due_date?->format('Y-m-d')) }}" required>
                </x-form-group>
                <div class="invoice-status-actions">
                    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-g">Annulla</a>
                    <button type="submit" class="btn btn-p">Salva scadenza</button>
                </div>
            </form>
        </x-panel>
    </div>
</x-app-layout>
