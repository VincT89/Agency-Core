<?php

namespace App\Domain\Quotes;

use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReviseQuoteAction
{
    public function execute(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote) {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            Gate::authorize('revise', $quote);
            $previous = $quote;
            while ($next = $previous->nextQuote()->withTrashed()->lockForUpdate()->first()) {
                if (! $next->trashed()) {
                    return $next;
                }
                $previous = $next;
            }
            $revision = Quote::create($quote->only([...QuoteDocument::FIELDS, 'issuer_snapshot']) + [
                'client_id' => $quote->client_id, 'ticket_id' => $quote->ticket_id,
                'project_id' => $quote->project_id, 'previous_quote_id' => $previous->id,
                'revision' => $previous->revision + 1, 'created_by' => auth()->id(),
                'title' => $quote->title, 'notes' => $quote->notes, 'total' => $quote->total, 'status' => 'draft',
            ]);
            foreach ($quote->items as $item) {
                $revision->items()->create($item->only(['name', 'summary', 'description', 'delivery_summary', 'delivery_terms', 'quantity', 'unit_price', 'total', 'sort_order']));
            }

            return $revision;
        });
    }
}
