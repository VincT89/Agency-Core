<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends Model
{
    public const STATUSES = [
        'draft',
        'issued',
        'partially_paid',
        'paid',
        'overdue',
        'cancelled',
    ];

    protected $fillable = [
        'client_id',
        'project_id',
        'created_by',
        'number',
        'issue_date',
        'due_date',
        'status',
        'currency',
        'subtotal',
        'tax_amount',
        'total',
        'paid_total',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_total' => 'decimal:2',
    ];

    public function getResidualAttribute(): float
    {
        return (float) max(0, $this->total - $this->paid_total);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Bozza',
            'issued' => 'Emessa',
            'partially_paid' => 'Pagamento parziale',
            'paid' => 'Pagata',
            'overdue' => 'Scaduta',
            'cancelled' => 'Annullata',
            default => ucfirst((string) $this->status),
        };
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
