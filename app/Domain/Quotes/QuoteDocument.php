<?php

namespace App\Domain\Quotes;

use App\Models\BillingProfile;
use App\Models\Quote;

class QuoteDocument
{
    public const FIELDS = ['document_reference', 'document_date', 'introduction', 'payment_terms', 'ai_instructions', 'price_note'];

    public const ISSUER_FIELDS = ['legal_name', 'address', 'postal_code', 'city', 'province', 'vat_number', 'tax_code'];

    public static function defaultIssuer(): array
    {
        return BillingProfile::current()?->only(self::ISSUER_FIELDS) ?? config('quotes.issuer');
    }

    public static function reference(Quote $quote): string
    {
        return $quote->document_reference ?: 'N'.str_pad((string) $quote->id, 5, '0', STR_PAD_LEFT);
    }
}
