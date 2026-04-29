<?php

namespace App\Domain\Finance\Queries;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

class InvoiceQuery
{
    // Costruisce la query per l'indice con filtri (ProjectSupremacyScope implicito)
    public function forIndex(array $filters): Builder
    {
        $query = Invoice::query()
            ->with(['client', 'project.client', 'creator', 'attachments'])
            ->latest('issue_date');

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'pending') {
                $query->whereIn('status', ['issued', 'partially_paid']);
            } else {
                $query->where('status', $filters['status']);
            }
        }
        if (!empty($filters['client_id'])) {
            $query->whereHas('project', function ($q) use ($filters) {
                $q->where('client_id', $filters['client_id']);
            });
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('issue_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('issue_date', '<=', $filters['end_date']);
        }
        
        if (!empty($filters['search'])) {
            $search = '%' . strtolower($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(number) LIKE ?', [$search])
                  ->orWhereHas('client', function ($cq) use ($search) {
                      $cq->whereRaw('LOWER(name) LIKE ?', [$search]);
                  });
            });
        }

        return $query;
    }

    // Bypass di sicurezza globale per l'identificazione di sistema delle fatture scadute
    public function forSystemDetection(): Builder
    {
        return Invoice::query()->withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class);
    }
}
