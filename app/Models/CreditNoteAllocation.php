<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteAllocation extends Model
{
    protected $fillable = ['credit_note_id', 'invoice_id', 'created_by', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
