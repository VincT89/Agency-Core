<?php

namespace App\Domain\Quotes;

use App\Models\Quote;
use App\Models\Ticket;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveQuoteAction
{
    public function execute(array $data, ?Quote $quote = null): Quote
    {
        return DB::transaction(function () use ($data, $quote) {
            $document = Arr::only($data, QuoteDocument::FIELDS);
            if (array_key_exists('issuer', $data)) {
                $document['issuer_snapshot'] = Arr::only($data['issuer'], QuoteDocument::ISSUER_FIELDS);
            }
            if ($quote) {
                $quote = Quote::lockForUpdate()->findOrFail($quote->id);
                Gate::authorize('update', $quote);
                $quote->update(['title' => $data['title'], 'notes' => $data['notes'] ?? null] + $document);
            } else {
                Gate::authorize('create', Quote::class);
                $ticket = ! empty($data['ticket_id']) ? Ticket::lockForUpdate()->findOrFail($data['ticket_id']) : null;
                if ($ticket) {
                    Gate::authorize('view', $ticket);
                    abort_unless($ticket->type === 'quote' && (int) $ticket->client_id === (int) $data['client_id'], 422);
                }
                $quote = Quote::create($document + [
                    'client_id' => $data['client_id'], 'ticket_id' => $ticket?->id,
                    'project_id' => $ticket?->project_id, 'created_by' => auth()->id(),
                    'title' => $data['title'], 'notes' => $data['notes'] ?? null, 'status' => 'draft',
                    'issuer_snapshot' => QuoteDocument::defaultIssuer(),
                    'document_date' => now()->toDateString(),
                ]);
            }
            $quote->items()->delete();
            $total = 0;
            foreach (array_values($data['items']) as $index => $item) {
                $item['total'] = QuoteAmounts::lineTotal((string) $item['quantity'], (string) $item['unit_price']);
                $item['sort_order'] = $index;
                $total += QuoteAmounts::hundredths($item['total']);
                $quote->items()->create($item);
            }
            $quote->update(['total' => QuoteAmounts::decimal($total)]);

            return $quote;
        });
    }
}
