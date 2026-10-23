<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class EconomicSummaryService
{
    public function __construct(private FinanceReportQuery $reports) {}

    public function globalSummary(User $user, ?string $from = null, ?string $to = null, array $filters = []): array
    {
        if (! $user->canAccessFinance()) {
            return ['total_invoiced' => 0, 'total_collected' => 0, 'total_outstanding' => 0, 'actual_outstanding' => 0, 'theoretical_outstanding' => 0, 'total_expenses' => 0, 'invoices_count' => 0, 'unallocated_credit' => 0];
        }
        return $this->reports->totals(array_filter(array_replace($filters, compact('from', 'to')), fn ($value) => $value !== null));
    }

    public function summaryByClient(User $user, ?string $from = null, ?string $to = null): Collection
    {
        return $user->canAccessFinance() ? $this->reports->groups('clients', array_filter(compact('from', 'to')))
            ->orderByDesc('total_invoiced')->get() : collect();
    }

    public function summaryByProject(User $user, ?string $from = null, ?string $to = null): Collection
    {
        return $user->canAccessFinance() ? $this->reports->groups('projects', array_filter(compact('from', 'to')))
            ->orderByDesc('total_invoiced')->get() : collect();
    }
}
