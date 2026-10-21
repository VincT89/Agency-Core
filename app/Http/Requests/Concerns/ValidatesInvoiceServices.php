<?php

namespace App\Http\Requests\Concerns;

use App\Models\HostingService;
use Illuminate\Validation\Validator;

trait ValidatesInvoiceServices
{
    protected function validateInvoiceServices(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        $lines = collect($this->input('items', []));
        if ($this->route('invoice')?->hosting_service_id && $lines->contains(fn ($line) => filled($line['hosting_service_id'] ?? null))) {
            $validator->errors()->add('items', 'Il servizio è collegato all’intera fattura: non può essere assegnato anche alle singole voci.');
            return;
        }
        $services = HostingService::withTrashed()->whereIn('id', $lines->pluck('hosting_service_id')->filter())
            ->get(['id', 'client_id', 'deleted_at'])->keyBy('id');
        $existing = $this->route('invoice')?->items()->pluck('hosting_service_id', 'id') ?? collect();
        foreach ($lines as $index => $line) {
            if (blank($line['hosting_service_id'] ?? null)) {
                continue;
            }
            $service = $services->get($line['hosting_service_id']);
            if (! $service || $service->client_id !== (int) $this->input('client_id')
                || ($service->trashed() && (int) $existing->get($line['id'] ?? 0) !== $service->id)) {
                $validator->errors()->add("items.$index.hosting_service_id", 'Il dominio o hosting deve appartenere al cliente della fattura.');
            }
        }
    }
}
