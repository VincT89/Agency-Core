<?php

namespace App\Models;

use App\Enums\Finance\ElectronicInvoiceTransmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectronicInvoiceTransmission extends Model
{
    protected $fillable = [
        'invoice_id',
        'submitted_by',
        'provider',
        'environment',
        'mode',
        'attempt_number',
        'status',
        'xml_filename',
        'xml_hash',
        'xml_content',
        'request_identifier',
        'upload_filename',
        'provider_invoice_id',
        'sdi_id',
        'provider_status',
        'error_code',
        'error_message',
        'response_payload',
        'submitted_at',
        'last_status_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ElectronicInvoiceTransmissionStatus::class,
            'attempt_number' => 'integer',
            'xml_content' => 'encrypted',
            'response_payload' => 'array',
            'submitted_at' => 'datetime',
            'last_status_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ElectronicInvoiceEvent::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }
}
