<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectronicInvoiceEvent extends Model
{
    protected $fillable = [
        'electronic_invoice_transmission_id',
        'event_key',
        'source',
        'type',
        'status',
        'provider_filename',
        'sdi_id',
        'payload',
        'document_content',
        'document_hash',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'document_content' => 'encrypted',
            'occurred_at' => 'datetime',
        ];
    }

    public function transmission(): BelongsTo
    {
        return $this->belongsTo(ElectronicInvoiceTransmission::class);
    }
}
