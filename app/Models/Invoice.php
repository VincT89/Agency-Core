<?php

namespace App\Models;

use App\Domain\Finance\Services\CashAmount;
use App\Enums\Finance\InvoiceFiscalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasFactory;

    public const OUTSTANDING_SQL = 'CASE WHEN COALESCE(payable_total, total) - credited_total - paid_total > 0 THEN COALESCE(payable_total, total) - credited_total - paid_total ELSE 0 END';
    public const OUTSTANDING_STATUSES = ['issued', 'partially_paid', 'overdue'];

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
        'payable_total',
        'credited_total',
        'notes',
        'fiscal_status',
        'fiscal_document_type',
        'fiscal_number',
        'fiscal_sequence_number',
        'fiscal_locked_at',
        'fiscal_snapshot',
        'import_fingerprint',
        'import_source',
        'import_metadata',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'payable_total' => 'decimal:2',
        'credited_total' => 'decimal:2',
        'fiscal_status' => InvoiceFiscalStatus::class,
        'fiscal_sequence_number' => 'integer',
        'fiscal_locked_at' => 'datetime',
        'fiscal_snapshot' => 'array',
        'import_metadata' => 'array',
    ];

    public function getResidualAttribute(): float
    {
        if ($this->status === 'cancelled') {
            return 0;
        }

        return max(0, CashAmount::cents($this->receivable_total) - CashAmount::cents($this->paid_total)) / 100;
    }

    public function scopeAwaitingPayment(Builder $query): Builder
    {
        return $query->whereIn('status', self::OUTSTANDING_STATUSES)->whereRaw(self::OUTSTANDING_SQL.' > 0');
    }

    public function scopePastDue(Builder $query): Builder
    {
        /** Deadline indicators must work before the scheduled job updates stored invoice statuses. */
        return $query->awaitingPayment()->whereDate('due_date', '<', today());
    }

    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, self::OUTSTANDING_STATUSES, true)
            && $this->due_date !== null && $this->due_date->lt(today()) && $this->residual > 0;
    }

    public function getReceivableTotalAttribute(): float
    {
        return max(0, CashAmount::cents($this->payable_total ?? $this->total) - CashAmount::cents($this->credited_total ?? 0)) / 100;
    }

    public function getCustomerCreditAttribute(): float
    {
        return max(0, CashAmount::cents($this->paid_total) - CashAmount::cents($this->receivable_total)) / 100;
    }

    public function getDisplayNumberAttribute(): string
    {
        return $this->import_metadata['number'] ?? $this->fiscal_number ?? $this->number;
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
            'credited' => 'Stornata',
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

    public function creditAllocations(): HasMany
    {
        return $this->hasMany(CreditNoteAllocation::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function electronicInvoiceTransmissions(): HasMany
    {
        return $this->hasMany(ElectronicInvoiceTransmission::class);
    }

    public function latestElectronicInvoiceTransmission(): HasOne
    {
        return $this->hasOne(ElectronicInvoiceTransmission::class)->latestOfMany();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canManageMarketing()) {
            return $query;
        }

        return $query->whereHas('project.users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }
}
