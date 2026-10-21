<?php

namespace App\Domain\Finance\Queries;

use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InvoiceDocumentQuery
{
    public function __construct(private InvoiceQuery $invoiceQuery) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $invoices = $this->invoiceQuery->forIndex($filters)->withoutEagerLoads()->reorder();
        $number = $invoices->getQuery()->getGrammar()->wrap('invoices.import_metadata->number');
        $invoices->select(['invoices.id', 'invoices.issue_date', 'invoices.total', 'invoices.fiscal_document_type'])
            ->selectRaw("'invoice' as record_kind, COALESCE($number, invoices.fiscal_number, invoices.number) AS document_number");
        $notes = CreditNote::query()->select(['credit_notes.id', 'credit_notes.issue_date', 'credit_notes.total', 'credit_notes.fiscal_document_type'])
            ->selectRaw("'credit_note' as record_kind, number AS document_number");
        foreach (['min_amount' => '>=', 'max_amount' => '<='] as $field => $operator) {
            $notes->when(filled($filters[$field] ?? null), fn ($q) => $q->where('total', $operator, $filters[$field]));
        }
        if (filled($filters['project_id'] ?? null) || filled($filters['hosting_service_id'] ?? null) || filled($filters['service_type'] ?? null)) {
            $matchingInvoices = $this->invoiceQuery->forIndex(collect($filters)->only(['project_id', 'hosting_service_id', 'service_type'])->all())
                ->withoutEagerLoads()->reorder()->select('invoices.id');
            $notes->whereHas('allocations', fn ($q) => $q->whereIn('invoice_id', $matchingInvoices));
        }

        if (($filters['document_type'] ?? '') === 'TD04') {
            $invoices->whereRaw('1 = 0');
        }
        if (($filters['document_type'] ?? '') === 'TD01') {
            $invoices->where('fiscal_document_type', 'TD01');
        }
        /** Collection states apply to invoices; a credit note has allocation status instead. */
        if (($filters['document_type'] ?? '') === 'TD01' || filled($filters['status'] ?? '')
            || (filled($filters['fiscal_status'] ?? '') && $filters['fiscal_status'] !== 'imported')) {
            $notes->whereRaw('1 = 0');
        }
        if (filled($filters['client_id'] ?? '')) {
            $notes->where('client_id', $filters['client_id']);
        }
        if (filled($filters['start_date'] ?? '')) {
            $notes->whereDate('issue_date', '>=', $filters['start_date']);
        }
        if (filled($filters['end_date'] ?? '')) {
            $notes->whereDate('issue_date', '<=', $filters['end_date']);
        }
        if (filled($filters['document_number'] ?? '')) {
            $number = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['document_number'])).'%';
            $notes->whereRaw("LOWER(number) LIKE ? ESCAPE '!'", [$number]);
        }
        if (filled($filters['search'] ?? '')) {
            $search = '%'.mb_strtolower($filters['search']).'%';
            $notes->where(fn ($query) => $query->whereRaw('LOWER(number) LIKE ?', [$search])
                ->orWhereHas('client', fn ($clients) => $clients->where(fn ($client) => $client
                    ->whereRaw('LOWER(name) LIKE ?', [$search])->orWhereRaw('LOWER(company_name) LIKE ?', [$search]))));
        }

        /** Paginate the combined identities in SQL before loading models, preserving separate records and bounded memory. */
        $sort = $filters['sort'] ?? 'date_desc';
        $column = match ($sort) { 'number_asc', 'number_desc' => 'document_number', 'type_asc', 'type_desc' => 'fiscal_document_type', 'amount_asc', 'amount_desc' => 'total', default => 'issue_date' };
        $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';
        $ordered = DB::query()->fromSub($invoices->toBase()->unionAll($notes->toBase()), 'invoice_documents');
        if ($column === 'document_number') {
            /** Aruba prefixes retain their original text while their sequence is ordered numerically (2 before 10). */
            $aruba = "(UPPER(document_number) LIKE 'FPA %/%' OR UPPER(document_number) LIKE 'FPR %/%')";
            $sequence = DB::connection()->getDriverName() === 'sqlite'
                ? 'CAST(SUBSTR(document_number, 5, INSTR(document_number, \'/\') - 5) AS INTEGER)'
                : "CAST(SUBSTRING_INDEX(SUBSTRING(document_number, 5), '/', 1) AS UNSIGNED)";
            $year = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y', issue_date)" : 'YEAR(issue_date)';
            $ordered->orderByRaw("CASE WHEN $aruba THEN SUBSTR(document_number, 1, 3) ELSE document_number END $direction")
                ->orderByRaw("CASE WHEN $aruba THEN $year ELSE NULL END $direction")
                ->orderByRaw("CASE WHEN $aruba THEN $sequence ELSE NULL END $direction");
        } else {
            $ordered->orderBy($column, $direction);
            if ($column === 'fiscal_document_type') {
                $ordered->orderByDesc('issue_date');
            }
        }
        $documents = $ordered->orderBy('record_kind')->orderByDesc('id')->paginate(15)->withQueryString();
        $rows = $documents->getCollection();
        $invoiceModels = Invoice::with(['client', 'project', 'hostingService'])->whereKey($rows->where('record_kind', 'invoice')->pluck('id'))->get()->keyBy('id');
        $noteModels = CreditNote::with(['client', 'allocations.invoice'])->whereKey($rows->where('record_kind', 'credit_note')->pluck('id'))->get()->keyBy('id');
        $documents->setCollection($rows->map(fn ($row) => $row->record_kind === 'invoice'
            ? $invoiceModels->get($row->id) : $noteModels->get($row->id))->filter()->values());

        return $documents;
    }
}
