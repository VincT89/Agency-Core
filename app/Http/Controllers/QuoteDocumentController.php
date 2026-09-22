<?php

namespace App\Http\Controllers;

use App\Domain\Quotes\QuoteDocument;
use App\Models\Quote;
use Illuminate\Http\Response;

class QuoteDocumentController extends Controller
{
    public function __invoke(Quote $quote): Response
    {
        $this->authorize('view', $quote);
        $quote->load(['items', 'client']);

        return response()->view('quotes.document', [
            'quote' => $quote,
            'issuer' => $quote->issuer_snapshot ?? QuoteDocument::defaultIssuer(),
            'customer' => $quote->client_snapshot ?? $quote->client->only([
                'name', 'company_name', 'reference_person', 'address', 'city', 'postal_code', 'province', 'country', 'vat_number', 'tax_code', 'email', 'phone',
            ]),
            'reference' => QuoteDocument::reference($quote),
        ])->header('Cache-Control', 'private, no-store');
    }
}
