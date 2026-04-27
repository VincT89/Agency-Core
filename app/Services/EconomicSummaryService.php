<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EconomicSummaryService
{
    protected array $includedStatuses = [
        'issued',
        'partially_paid',
        'paid',
        'overdue',
    ];

    /**
     * Applica il perimetro di autorizzazione Finance
     */
    protected function applyRolePerimeter(Builder $query, User $user): Builder
    {
        if ($user->canAccessFinance()) {
            return $query;
        }

        return $query->whereNull('id');
    }
    
    protected function applyRolePerimeterSafe(Builder $query, User $user, string $tableName): Builder
    {
         if ($user->canAccessFinance()) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Filtro temporale generico
     */
    protected function applyPeriod(Builder $query, string $dateColumn, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->where($dateColumn, '>=', $from);
        }
        if ($to) {
            $query->where($dateColumn, '<=', $to);
        }
        return $query;
    }

    public function globalSummary(User $user, ?string $from = null, ?string $to = null): array
    {
        // 1. FATTURATO E DA INCASSARE (Su base fatture, filtrate per issue_date)
        $invoicesQuery = Invoice::query()->whereIn('status', $this->includedStatuses);
        $invoicesQuery = $this->applyRolePerimeterSafe($invoicesQuery, $user, 'invoices');
        $invoicesQuery = $this->applyPeriod($invoicesQuery, 'issue_date', $from, $to);
        
        $invoiceStats = $invoicesQuery->selectRaw('
            COUNT(id) as invoices_count,
            SUM(total) as total_invoiced,
            SUM(total - paid_total) as total_outstanding
        ')->first();

        // 2. INCASSATO (Su base pagamenti, filtrati per payment_date)
        $paymentsQuery = Payment::query();
        $paymentsQuery = $this->applyRolePerimeterSafe($paymentsQuery, $user, 'payments');
        $paymentsQuery = $this->applyPeriod($paymentsQuery, 'payment_date', $from, $to);
        
        $totalCollected = (float) $paymentsQuery->sum('amount');

        return [
            'total_invoiced' => round((float) $invoiceStats->total_invoiced, 2),
            'total_collected' => round($totalCollected, 2),
            'total_outstanding' => round((float) $invoiceStats->total_outstanding, 2),
            'invoices_count' => (int) $invoiceStats->invoices_count,
        ];
    }

    public function summaryByClient(User $user, ?string $from = null, ?string $to = null): Collection
    {
        // 1. INVOICES (Aggregate by client)
        $invoicesQuery = Invoice::query()->whereIn('status', $this->includedStatuses);
        $invoicesQuery = $this->applyRolePerimeterSafe($invoicesQuery, $user, 'invoices');
        $invoicesQuery = $this->applyPeriod($invoicesQuery, 'issue_date', $from, $to);

        $invoicesByClient = $invoicesQuery
            ->selectRaw('
                client_id,
                COUNT(id) as invoices_count,
                SUM(total) as total_invoiced,
                SUM(total - paid_total) as total_outstanding
            ')
            ->groupBy('client_id')
            ->get()
            ->keyBy('client_id');

        // 2. PAYMENTS (Aggregate by client)
        $paymentsQuery = Payment::query();
        $paymentsQuery = $this->applyRolePerimeterSafe($paymentsQuery, $user, 'payments');
        $paymentsQuery = $this->applyPeriod($paymentsQuery, 'payment_date', $from, $to);
        
        $paymentsByClient = $paymentsQuery
            ->selectRaw('
                client_id,
                SUM(amount) as total_collected
            ')
            ->groupBy('client_id')
            ->get()
            ->keyBy('client_id');

        // 3. MERGE in PHP su aggregati piccolissimi
        $clientIds = collect(array_keys($invoicesByClient->toArray()))
            ->merge(array_keys($paymentsByClient->toArray()))
            ->unique();

        $clientNames = Client::query()
            ->whereIn('id', $clientIds)
            ->pluck('name', 'id');

        return $clientIds->map(function ($clientId) use ($invoicesByClient, $paymentsByClient, $clientNames) {
            $inv = $invoicesByClient->get($clientId);
            $pay = $paymentsByClient->get($clientId);

            return (object) [
                'client_id' => $clientId,
                'client_name' => $clientNames->get($clientId, 'N/D'),
                'invoices_count' => $inv ? (int) $inv->invoices_count : 0,
                'total_invoiced' => $inv ? (float) $inv->total_invoiced : 0.0,
                'total_outstanding' => $inv ? (float) $inv->total_outstanding : 0.0,
                'total_collected' => $pay ? (float) $pay->total_collected : 0.0,
            ];
        })->sortByDesc('total_invoiced')->values();
    }

    public function summaryByProject(User $user, ?string $from = null, ?string $to = null): Collection
    {
        // 1. INVOICES
        $invoicesQuery = Invoice::query()->whereIn('status', $this->includedStatuses);
        $invoicesQuery = $this->applyRolePerimeterSafe($invoicesQuery, $user, 'invoices');
        $invoicesQuery = $this->applyPeriod($invoicesQuery, 'issue_date', $from, $to);
        
        $invoicesByProject = $invoicesQuery
            ->whereNotNull('project_id')
            ->selectRaw('
                project_id,
                COUNT(id) as invoices_count,
                SUM(total) as total_invoiced,
                SUM(total - paid_total) as total_outstanding
            ')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        // 2. PAYMENTS
        $paymentsQuery = Payment::query();
        $paymentsQuery = $this->applyRolePerimeterSafe($paymentsQuery, $user, 'payments');
        $paymentsQuery = $this->applyPeriod($paymentsQuery, 'payment_date', $from, $to);
        
        $paymentsByProject = $paymentsQuery
            ->whereNotNull('project_id')
            ->selectRaw('
                project_id,
                SUM(amount) as total_collected
            ')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        // 3. MERGE
        $projectIds = collect(array_keys($invoicesByProject->toArray()))
            ->merge(array_keys($paymentsByProject->toArray()))
            ->unique();

        $projectsInfo = Project::query()
            ->whereIn('id', $projectIds)
            ->with('client')
            ->get()
            ->keyBy('id');

        return $projectIds->map(function ($projectId) use ($invoicesByProject, $paymentsByProject, $projectsInfo) {
            $inv = $invoicesByProject->get($projectId);
            $pay = $paymentsByProject->get($projectId);
            $proj = $projectsInfo->get($projectId);

            return (object) [
                'project_id' => $projectId,
                'project_name' => $proj ? $proj->name : 'N/D',
                'client_name' => ($proj && $proj->client) ? $proj->client->name : 'N/D',
                'invoices_count' => $inv ? (int) $inv->invoices_count : 0,
                'total_invoiced' => $inv ? (float) $inv->total_invoiced : 0.0,
                'total_outstanding' => $inv ? (float) $inv->total_outstanding : 0.0,
                'total_collected' => $pay ? (float) $pay->total_collected : 0.0,
            ];
        })->sortByDesc('total_invoiced')->values();
    }
}