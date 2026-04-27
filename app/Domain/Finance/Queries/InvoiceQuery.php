<?php

namespace App\Domain\Finance\Queries;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

class InvoiceQuery
{
    /**
     * Builds the standard index query with given filters.
     * Relies on ProjectSupremacyScope implicitly.
     */
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

    /**
     * System detection query specifically meant to find overdue invoices.
     * Bypasses UI scopes via fail-closed bypass.
     */
    public function forSystemDetection(): Builder
    {
        return Invoice::query()->withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class);
    }
}
