<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\HostingService;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FinanceReportQuery
{
    public const STATUSES = ['issued', 'partially_paid', 'paid', 'overdue', 'credited'];

    private function invoiceScope($query, array $filters)
    {
        $query->whereIn('invoices.status', self::STATUSES);
        if (filled($filters['invoice_status'] ?? null)) {
            $filters['invoice_status'] === 'overdue'
                ? $query->pastDue()
                : $query->where('invoices.status', $filters['invoice_status']);
        }
        return $query;
    }

    public function events(array $filters = [], bool $projectCredits = false): Builder
    {
        $invoices = $this->invoiceScope(Invoice::query(), $filters);
        $number = $invoices->getQuery()->getGrammar()->wrap('invoices.import_metadata->number');
        $invoices->selectRaw("'invoice' AS kind, invoices.id AS record_id, COALESCE($number, invoices.fiscal_number, invoices.number) AS label,
            invoices.issue_date AS event_date, invoices.client_id, invoices.project_id,
            invoices.subtotal AS net, 0 AS collected, 0 AS costs, 1 AS invoice_count, invoices.total AS document_amount");
        $notes = CreditNote::query();
        if (filled($filters['invoice_status'] ?? null)) {
            $notes->whereHas('allocations.invoice', fn ($q) => $this->invoiceScope($q, $filters));
        }
        if ($projectCredits || filled($filters['project_id'] ?? null)) {
            /** Cumulative rounding assigns every taxable cent once, even when a note spans several projects. */
            $prior = '(SELECT COALESCE(SUM(previous.amount), 0) FROM credit_note_allocations previous
                WHERE previous.credit_note_id = ca.credit_note_id AND previous.id < ca.id)';
            $taxable = "ROUND(credit_notes.subtotal * ($prior + ca.amount) / NULLIF(credit_notes.payable_total, 0), 2)
                - ROUND(credit_notes.subtotal * $prior / NULLIF(credit_notes.payable_total, 0), 2)";
            $notes->join('credit_note_allocations as ca', 'ca.credit_note_id', '=', 'credit_notes.id')
                ->join('invoices as ci', 'ci.id', '=', 'ca.invoice_id')->whereNotNull('ci.project_id');
            if (filled($filters['invoice_status'] ?? null)) {
                $notes->whereIn('ci.id', $this->invoiceScope(Invoice::query(), $filters)->select('invoices.id'));
            }
            $notes->selectRaw("'credit_note' AS kind, credit_notes.id AS record_id, credit_notes.number AS label,
                credit_notes.issue_date AS event_date, credit_notes.client_id, ci.project_id,
                -($taxable) AS net, 0 AS collected, 0 AS costs, 0 AS invoice_count, credit_notes.total AS document_amount");
        } else {
            $notes->selectRaw("'credit_note' AS kind, credit_notes.id AS record_id, credit_notes.number AS label,
                credit_notes.issue_date AS event_date, credit_notes.client_id, NULL AS project_id,
                -credit_notes.subtotal AS net, 0 AS collected, 0 AS costs, 0 AS invoice_count, credit_notes.total AS document_amount");
        }
        $payments = Payment::query()->leftJoin('invoices as pi', 'pi.id', '=', 'payments.invoice_id');
        if (filled($filters['invoice_status'] ?? null)) {
            $payments->whereIn('payments.invoice_id', $this->invoiceScope(Invoice::query(), $filters)->select('invoices.id'));
        }
        $paymentNumber = $payments->getQuery()->getGrammar()->wrap('pi.import_metadata->number');
        $payments->selectRaw("'payment' AS kind, payments.id AS record_id,
            COALESCE($paymentNumber, pi.fiscal_number, pi.number, payments.reference, 'Pagamento') AS label,
            payments.payment_date AS event_date, payments.client_id,
            CASE WHEN payments.invoice_id IS NULL THEN payments.project_id ELSE pi.project_id END AS project_id,
            0 AS net, payments.amount AS collected, 0 AS costs, 0 AS invoice_count,
            COALESCE(pi.total, payments.amount) AS document_amount");
        $expenses = $this->expenseEvents($filters);
        $union = $invoices->toBase()->unionAll($notes->toBase())->unionAll($payments->toBase())->unionAll($expenses->toBase());
        return $this->filterEvents(DB::query()->fromSub($union, 'financial_events'), $filters);
    }

    public function expenseEvents(array $filters = [])
    {
        $query = Expense::query()->whereIn('expenses.status', ['pending', 'paid'])
            ->leftJoin('projects as ep', fn ($join) => $join->on('ep.id', '=', 'expenses.expenseable_id')->where('expenses.expenseable_type', Project::class))
            ->leftJoin('hosting_services as eh', fn ($join) => $join->on('eh.id', '=', 'expenses.expenseable_id')->where('expenses.expenseable_type', HostingService::class))
            ->leftJoin('tickets as et', fn ($join) => $join->on('et.id', '=', 'expenses.expenseable_id')->where('expenses.expenseable_type', Ticket::class))
            ->leftJoin('tasks as ea', fn ($join) => $join->on('ea.id', '=', 'expenses.expenseable_id')->where('expenses.expenseable_type', Task::class))
            ->leftJoin('projects as eap', 'eap.id', '=', 'ea.project_id')
            ->leftJoin('tickets as eat', 'eat.id', '=', 'ea.ticket_id');
        foreach (['category_id', 'subcategory_id'] as $field) {
            $query->when(filled($filters[$field] ?? null), fn ($q) => $q->where('expenses.'.$field, $filters[$field]));
        }
        $query->when(filled($filters['expense_status'] ?? null), fn ($q) => $q->where('expenses.status', $filters['expense_status']));
        return $query->selectRaw("'expense' AS kind, expenses.id AS record_id, expenses.title AS label,
            expenses.expense_date AS event_date,
            CASE WHEN expenses.expenseable_type = ? THEN expenses.expenseable_id ELSE COALESCE(ep.client_id, eh.client_id, et.client_id, eap.client_id, eat.client_id) END AS client_id,
            COALESCE(ep.id, et.project_id, ea.project_id, eat.project_id) AS project_id,
            0 AS net, 0 AS collected, expenses.amount AS costs, 0 AS invoice_count, expenses.amount AS document_amount", [Client::class]);
    }

    private function filterEvents(Builder $query, array $filters): Builder
    {
        foreach (['client_id', 'project_id'] as $column) {
            $query->when(filled($filters[$column] ?? null), fn ($q) => $q->where($column, $filters[$column]));
        }
        $query->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('event_date', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('event_date', '<=', $filters['to']))
            ->when(filled($filters['min_amount'] ?? null), fn ($q) => $q->where('document_amount', '>=', $filters['min_amount']))
            ->when(filled($filters['max_amount'] ?? null), fn ($q) => $q->where('document_amount', '<=', $filters['max_amount']));
        if (filled($filters['q'] ?? null)) {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q'])).'%';
            $query->whereRaw("LOWER(label) LIKE ? ESCAPE '!'", [$search]);
        }
        return $query;
    }

    public function totals(array $filters = []): array
    {
        $row = $this->events($filters)->selectRaw('COALESCE(SUM(net), 0) AS net, COALESCE(SUM(collected), 0) AS collected,
            COALESCE(SUM(costs), 0) AS costs, COALESCE(SUM(invoice_count), 0) AS invoices_count')->first();
        $net = round((float) $row->net, 2);
        $collected = round((float) $row->collected, 2);
        return [
            'total_invoiced' => $net, 'total_collected' => $collected,
            'total_outstanding' => ReportInvoiceAmounts::outstanding($net, $collected),
            'total_expenses' => round((float) $row->costs, 2), 'invoices_count' => (int) $row->invoices_count,
            'unallocated_credit' => $this->unallocatedCredit($filters),
        ];
    }

    private function unallocatedCredit(array $filters): float
    {
        $notes = $this->events($filters)->where('kind', 'credit_note')->select('record_id');
        $remaining = 'credit_notes.payable_total - (SELECT COALESCE(SUM(amount), 0) FROM credit_note_allocations WHERE credit_note_id = credit_notes.id)';
        return (float) CreditNote::whereIn('id', $notes)
            ->selectRaw("COALESCE(SUM(CASE WHEN ($remaining) > 0 THEN ($remaining) ELSE 0 END), 0) AS remaining")->value('remaining');
    }

    public function groups(string $type, array $filters = []): Builder
    {
        $project = $type === 'projects';
        $column = $project ? 'project_id' : 'client_id';
        $metrics = $this->events($filters, $project)->whereNotNull($column)
            ->selectRaw("$column, ROUND(SUM(net), 2) AS total_invoiced, ROUND(SUM(collected), 2) AS total_collected,
                ROUND(SUM(costs), 2) AS total_expenses, SUM(invoice_count) AS invoices_count")->groupBy($column);
        $query = DB::query()->fromSub($metrics, 'metrics');
        if ($project) {
            $query->join('projects', 'projects.id', '=', 'metrics.project_id')->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
                ->select('metrics.*', 'projects.name as project_name')
                ->selectRaw('CASE WHEN projects.system_key = ? THEN ? ELSE clients.name END AS client_name', [Project::INVOICE_CONTAINER_KEY, 'Tutti i clienti']);
        } else {
            $query->join('clients', 'clients.id', '=', 'metrics.client_id')->select('metrics.*', 'clients.name as client_name');
        }
        return $query->selectRaw('ROUND(ROUND(metrics.total_invoiced * 1.22, 2) - metrics.total_collected, 2) AS total_outstanding');
    }

    public function categoryGroups(array $filters = []): Builder
    {
        $expenseIds = $this->events($filters)->where('kind', 'expense')->select('record_id');
        return DB::table('expenses')->whereIn('expenses.id', $expenseIds)
            ->leftJoin('expense_categories as category', 'category.id', '=', 'expenses.category_id')
            ->selectRaw("expenses.category_id, COALESCE(category.name, 'Senza categoria') AS name,
                SUM(expenses.amount) AS total_expenses, COUNT(*) AS expenses_count")
            ->groupBy('expenses.category_id', 'category.name');
    }

    public function documents(array $filters = []): Builder
    {
        return $this->events($filters)->selectRaw('kind, record_id, label, event_date,
            SUM(net) AS net, SUM(collected) AS collected, SUM(costs) AS costs')
            ->groupBy('kind', 'record_id', 'label', 'event_date')->orderByDesc('event_date')->orderBy('kind')->orderByDesc('record_id');
    }

    public function monthly(array $filters = []): array
    {
        $expression = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', event_date)" : "DATE_FORMAT(event_date, '%Y-%m')";
        $rows = $this->events($filters)->selectRaw("$expression AS month, SUM(net) AS net, SUM(collected) AS collected, SUM(costs) AS costs")
            ->groupBy('month')->orderBy('month')->get()->keyBy('month');
        $first = CarbonImmutable::parse($filters['from'] ?? ($rows->keys()->first() ? $rows->keys()->first().'-01' : ($filters['to'] ?? now()->startOfYear()->toDateString())))->startOfMonth();
        $last = CarbonImmutable::parse($filters['to'] ?? ($rows->keys()->last() ? $rows->keys()->last().'-01' : ($filters['from'] ?? now()->endOfYear()->toDateString())))->startOfMonth();
        $group = $filters['interval'] ?? 'month';
        /** Long histories use yearly buckets rather than rendering thousands of points in the browser. */
        if ($first->diffInMonths($last) > 240 && $group === 'month') {
            $group = 'year';
        }
        $buckets = [];
        for ($date = $first; $date->lte($last); $date = $date->addMonth()) {
            $key = match ($group) { 'year' => $date->format('Y'), 'quarter' => $date->format('Y').'-T'.$date->quarter, default => $date->format('Y-m') };
            $row = $rows->get($date->format('Y-m'));
            $buckets[$key] ??= ['net' => 0, 'collected' => 0, 'costs' => 0, 'from' => $date->toDateString(), 'to' => $date->endOfMonth()->toDateString()];
            foreach (['net', 'collected', 'costs'] as $metric) {
                $buckets[$key][$metric] += (float) ($row?->$metric ?? 0);
            }
            $buckets[$key]['to'] = $date->endOfMonth()->toDateString();
        }
        $labels = [];
        $data = [[], [], [], []];
        foreach ($buckets as $key => $bucket) {
            $labels[] = $group === 'month' ? ucfirst(CarbonImmutable::parse($key.'-01')->isoFormat('MMM YYYY')) : $key;
            $net = round($bucket['net'], 2);
            $collected = round($bucket['collected'], 2);
            $data[0][] = $net;
            $data[1][] = $collected;
            $data[2][] = ReportInvoiceAmounts::outstanding($net, $collected);
            $data[3][] = round($bucket['costs'], 2);
        }
        return ['labels' => $labels, 'periods' => array_values(array_map(fn ($b) => ['from' => $b['from'], 'to' => $b['to']], $buckets)),
            'series' => array_map(fn ($name, $index) => ['name' => $name, 'data' => $data[$index]],
                ['Fatturato netto (IVA esclusa)', 'Incassato', 'Da incassare (IVA 22%)', 'Spese'], [0, 1, 2, 3])];
    }
}
