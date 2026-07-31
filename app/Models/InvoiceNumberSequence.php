<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceNumberSequence extends Model
{
    protected $fillable = [
        'billing_profile_id',
        'year',
        'series',
        'next_number',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'next_number' => 'integer',
        ];
    }

    public function billingProfile(): BelongsTo
    {
        return $this->belongsTo(BillingProfile::class);
    }
}
