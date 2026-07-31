<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'billable_type',
        'billable_id',
        'description',
        'quantity',
        'unit_price',
        'total',
        'unit_of_measure',
        'vat_rate',
        'vat_nature',
        'vat_reference',
        'tax_amount',
        'total_with_tax',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_with_tax' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
}
