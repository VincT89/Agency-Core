<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceImportedServiceLink extends Model
{
    protected $fillable = ['invoice_id', 'line_index', 'hosting_service_id'];
    protected $casts = ['line_index' => 'integer'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function hostingService(): BelongsTo
    {
        return $this->belongsTo(HostingService::class)->withTrashed();
    }
}
