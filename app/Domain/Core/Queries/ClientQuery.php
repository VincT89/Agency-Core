<?php

namespace App\Domain\Core\Queries;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;

class ClientQuery
{
    public function forIndex(array $filters = []): Builder
    {
        $query = Client::query()->orderBy('name');
        if (auth()->user()?->isCommercial()) {
            $query->visibleTo(auth()->user());
        } else {
            $query->with('commercialUser:id,name')->withCount(['projects', 'tickets', 'invoices']);
        }

        return $this->applySearch($query, $filters['search'] ?? '');
    }

    public function forSearch(string $search): Builder
    {
        $query = Client::query();

        if (auth()->user()?->isCommercial()) {
            $query->visibleTo(auth()->user());
        }
        return $this->applySearch($query->orderBy('name'), $search);
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        if (trim($search) === '') {
            return $query;
        }

        $searchStr = '%'.mb_strtolower(trim($search)).'%';

        return $query->where(function (Builder $matches) use ($searchStr) {
            foreach (['name', 'company_name', 'reference_person', 'email', 'phone', 'vat_number', 'tax_code'] as $field) {
                $matches->orWhereRaw("LOWER({$field}) LIKE ?", [$searchStr]);
            }
        });
    }

    public function forDropdown(): Builder
    {
        if (auth()->user()?->isCommercial()) {
            return Client::visibleTo(auth()->user())->orderBy('name');
        }

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
