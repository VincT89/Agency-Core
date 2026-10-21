<?php

namespace App\Domain\Finance\Queries;

use App\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class InvoiceQuery
{
    // Costruisce la query per l'indice con filtri (ProjectSupremacyScope implicito)
    public function forIndex(array $filters): Builder
    {
        $query = Invoice::query()
            ->with(['client', 'project.client', 'creator', 'attachments'])
            ->latest('issue_date');

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'pending') {
                $query->whereIn('status', ['issued', 'partially_paid']);
            } elseif ($filters['status'] === 'overdue') {
                $query->pastDue();
            } else {
                $query->where('status', $filters['status']);
            }
        }
        if (! empty($filters['fiscal_status'])) {
            $query->where('fiscal_status', $filters['fiscal_status']);
        }
        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        $query->when(filled($filters['project_id'] ?? null), fn ($q) => $q->where('project_id', $filters['project_id']))
            ->when(filled($filters['min_amount'] ?? null), fn ($q) => $q->where('total', '>=', $filters['min_amount']))
            ->when(filled($filters['max_amount'] ?? null), fn ($q) => $q->where('total', '<=', $filters['max_amount']));
        if (filled($filters['hosting_service_id'] ?? null) || filled($filters['service_type'] ?? null)) {
            $services = fn ($services) => $services
                ->when(filled($filters['hosting_service_id'] ?? null), fn ($q) => $q->where('hosting_services.id', $filters['hosting_service_id']))
                ->when(filled($filters['service_type'] ?? null), fn ($q) => $q->withServiceType($filters['service_type']));
            $query->where(fn ($q) => $q->whereHas('hostingService', $services)
                ->orWhereHas('items.hostingService', $services)->orWhereHas('importedServiceLinks.hostingService', $services));
        }
        if (! empty($filters['start_date'])) {
            $query->whereDate('issue_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('issue_date', '<=', $filters['end_date']);
        }
        if (filled($filters['document_number'] ?? '')) {
            /** Match the number displayed to the user, preserving literal percent and underscore characters. */
            $number = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['document_number'])).'%';
            $importedNumber = $query->getQuery()->getGrammar()->wrap('import_metadata->number');
            $query->whereRaw("LOWER(COALESCE({$importedNumber}, fiscal_number, number)) LIKE ? ESCAPE '!'", [$number]);
        }

        if (! empty($filters['search'])) {
            $search = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(number) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(fiscal_number) LIKE ?', [$search])
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where(function ($clientQuery) use ($search) {
                            $clientQuery
                                ->whereRaw('LOWER(name) LIKE ?', [$search])
                                ->orWhereRaw('LOWER(company_name) LIKE ?', [$search]);
                        });
                    });
            });
        }

        return $query;
    }

    public function issuedAndExpired(CarbonInterface $date): Builder
    {
        return Invoice::query()
            ->where('status', 'issued')
            ->whereDate('due_date', '<', $date);
    }

    public function currentlyOverdue(): Builder
    {
        return Invoice::query()
            ->where('status', 'overdue');
    }
}
