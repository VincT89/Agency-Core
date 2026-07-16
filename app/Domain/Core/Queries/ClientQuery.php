<?php

namespace App\Domain\Core\Queries;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;

class ClientQuery
{
    public function forIndex(array $filters = []): Builder
    {
        $query = Client::query()
            ->withCount(['projects', 'tickets', 'invoices'])
            ->orderBy('name');

        if (!empty($filters['search'])) {
            $searchStr = '%' . strtolower($filters['search']) . '%';
            $query->where(function($q) use ($searchStr) {
                $q->whereRaw('LOWER(name) LIKE ?', [$searchStr])
                  ->orWhereRaw('LOWER(email) LIKE ?', [$searchStr])
                  ->orWhereRaw('LOWER(vat_number) LIKE ?', [$searchStr]);
            });
        }

        return $query;
    }

    public function forSearch(string $search): Builder
    {
        $query = Client::query();
        
        if (strlen($search) >= 1) {
            $searchStr = '%' . strtolower($search) . '%';
            $query->where(function($q) use ($searchStr) {
                $q->whereRaw('LOWER(name) LIKE ?', [$searchStr])
                  ->orWhereRaw('LOWER(company_name) LIKE ?', [$searchStr])
                  ->orWhereRaw('LOWER(email) LIKE ?', [$searchStr])
                  ->orWhereRaw('LOWER(phone) LIKE ?', [$searchStr])
                  ->orWhereRaw('LOWER(vat_number) LIKE ?', [$searchStr]);
            });
        }

        return $query;
    }

    public function forDropdown(): Builder
    {
        return Client::query()
            ->where(function ($q) {
                if (!auth()->check() || !auth()->user()->canBypassProjectScope()) {
                    $q->whereHas('projects');
                }
            })
            ->orderBy('name');
    }

    public function forInvoiceDropdown(): Builder
    {
        return Client::visibleTo(auth()->user())
            ->with('projects')
            ->orderBy('name');
    }
}
