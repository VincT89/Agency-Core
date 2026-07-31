<?php

namespace App\Models;

use App\Enums\Finance\InvoiceFiscalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends Model
{
    use HasFactory;

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
        'marketing_campaign_id',
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
        'fiscal_status',
        'fiscal_document_type',
        'fiscal_number',
        'fiscal_sequence_number',
        'fiscal_locked_at',
        'fiscal_snapshot',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'fiscal_status' => InvoiceFiscalStatus::class,
        'fiscal_sequence_number' => 'integer',
        'fiscal_locked_at' => 'datetime',
        'fiscal_snapshot' => 'array',
    ];

    public function getResidualAttribute(): float
    {
        return (float) max(0, $this->total - $this->paid_total);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Bozza gestionale',
            'issued' => 'Da incassare',
            'partially_paid' => 'Pagamento parziale',
            'paid' => 'Pagata',
            'overdue' => 'Scaduta',
            'cancelled' => 'Annullata',
            default => ucfirst((string) $this->status),
        };
    }

    public function getFiscalStatusLabelAttribute(): string
    {
        return ($this->fiscal_status ?? InvoiceFiscalStatus::NotPrepared)->label();
    }

    public function getFiscalBadgeStatusAttribute(): string
    {
        return ($this->fiscal_status ?? InvoiceFiscalStatus::NotPrepared)->badgeStatus();
    }

    public function isFiscalEditable(): bool
    {
        return ($this->fiscal_status ?? InvoiceFiscalStatus::NotPrepared)->allowsEditing();
    }

    public function hasStartedFiscalTransmission(): bool
    {
        return ($this->fiscal_status ?? InvoiceFiscalStatus::NotPrepared)->hasLeftTheGestionale();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function marketingCampaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
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

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canManageSystem() || $user->isMarketing()) {
            return $query;
        }

        return $query->whereHas('project.users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }
}
