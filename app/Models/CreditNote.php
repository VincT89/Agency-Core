<?php

namespace App\Models;

use App\Domain\Finance\Services\CashAmount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CreditNote extends Model
{
    protected $fillable = ['client_id', 'created_by', 'number', 'issue_date', 'fiscal_document_type', 'currency',
        'subtotal', 'tax_amount', 'total', 'payable_total', 'import_fingerprint', 'import_source', 'import_metadata'];

    protected $casts = ['issue_date' => 'date', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2',
        'total' => 'decimal:2', 'payable_total' => 'decimal:2', 'import_metadata' => 'array'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CreditNoteAllocation::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function getUnallocatedTotalAttribute(): float
    {
        $allocated = $this->relationLoaded('allocations') ? $this->allocations->sum('amount') : $this->allocations()->sum('amount');

        return max(0, CashAmount::cents($this->payable_total) - CashAmount::cents($allocated)) / 100;
    }
}
