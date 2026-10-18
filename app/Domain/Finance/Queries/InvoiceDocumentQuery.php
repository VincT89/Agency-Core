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
        $invoices = $this->invoiceQuery->forIndex($filters)->withoutEagerLoads()->reorder()
            ->select(['invoices.id', 'invoices.issue_date'])->selectRaw("'invoice' as record_kind");
        $notes = CreditNote::query()->select(['credit_notes.id', 'credit_notes.issue_date'])
            ->selectRaw("'credit_note' as record_kind");

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
        $documents = DB::query()->fromSub($invoices->toBase()->unionAll($notes->toBase()), 'invoice_documents')
            ->orderByDesc('issue_date')->orderBy('record_kind')->orderByDesc('id')->paginate(15)->withQueryString();
        $rows = $documents->getCollection();
        $invoiceModels = Invoice::with(['client', 'project'])->whereKey($rows->where('record_kind', 'invoice')->pluck('id'))->get()->keyBy('id');
        $noteModels = CreditNote::with(['client', 'allocations.invoice'])->whereKey($rows->where('record_kind', 'credit_note')->pluck('id'))->get()->keyBy('id');
        $documents->setCollection($rows->map(fn ($row) => $row->record_kind === 'invoice'
            ? $invoiceModels->get($row->id) : $noteModels->get($row->id))->filter()->values());

        return $documents;
    }
}
