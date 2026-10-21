@php
    $nativeServices = $invoice->items->filter(fn ($line) => $line->hosting_service_id)
        ->map(fn ($line) => ['service' => $line->hostingService, 'description' => $line->description, 'amount' => $line->total]);
    $importedServices = $invoice->importedServiceLinks->map(fn ($link) => [
        'service' => $link->hostingService,
        'description' => $invoice->import_metadata['items'][$link->line_index]['description'] ?? '',
        'amount' => $invoice->import_metadata['items'][$link->line_index]['total'] ?? 0,
    ]);
    $linkedServices = $nativeServices->concat($importedServices);
@endphp
@if($linkedServices->isNotEmpty())
    <div class="u-p-md">
        <h3 class="u-mb-sm">Domini e hosting collegati</h3>
        @foreach($linkedServices as $linked)
            <p class="u-mb-sm">
                @if($linked['service'] && !$linked['service']->trashed())<a href="{{ route('hosting-services.show', $linked['service']) }}">{{ $linked['service']->name }}</a>
                @else{{ $linked['service']?->name ?? 'Servizio non disponibile' }} (archiviato)@endif
                <span class="u-text-meta">{{ $linked['description'] }} · {{ number_format((float) $linked['amount'], 2, ',', '.') }} € imponibile</span>
            </p>
        @endforeach
    </div>
@endif
