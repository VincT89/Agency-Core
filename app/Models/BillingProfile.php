<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'profile_key',
    'legal_name',
    'vat_country_code',
    'vat_number',
    'tax_code',
    'fiscal_regime',
    'address',
    'postal_code',
    'city',
    'province',
    'country_code',
    'email',
    'pec',
    'recipient_code',
    'iban',
    'invoice_series',
    'initial_sequence',
])]
class BillingProfile extends Model
{
    public static function current(): ?self
    {
        return self::query()->where('profile_key', 'default')->first();
    }

    public function numberSequences(): HasMany
    {
        return $this->hasMany(InvoiceNumberSequence::class);
    }
}
