<?php

namespace App\Domain\Quotes;

use App\Models\Quote;
use App\Notifications\QuoteUpdatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ChangeQuoteStatusAction
{
    public function execute(Quote $quote, string $status): Quote
    {
        return DB::transaction(function () use ($quote, $status) {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            abort_unless(in_array($status, ['presented', 'accepted', 'rejected'], true), 422);
            Gate::authorize($status === 'presented' ? 'present' : 'respond', $quote);
            $changes = ['status' => $status, $status.'_at' => now()];
            if ($status === 'presented') {
                abort_unless($quote->items()->exists(), 422, 'Inserisci almeno un servizio.');
                $changes['client_snapshot'] = $quote->client->only([
                    'name', 'company_name', 'reference_person', 'email', 'phone', 'vat_number',
                    'tax_code', 'address', 'city', 'postal_code', 'province', 'country',
                ]);
            }
            $quote->update($changes);
            $commercial = $quote->client->commercialUser;
            if ($commercial?->isCommercial() && $commercial->status === 'active' && $commercial->id !== auth()->id()) {
                $commercial->notify(new QuoteUpdatedNotification($quote));
            }

            return $quote;
        });
    }
}
