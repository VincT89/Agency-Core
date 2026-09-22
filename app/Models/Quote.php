<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft' => 'Bozza', 'presented' => 'Presentata', 'accepted' => 'Accettata', 'rejected' => 'Rifiutata'];

    protected $fillable = ['client_id', 'ticket_id', 'created_by', 'previous_quote_id', 'project_id', 'revision', 'title', 'status', 'client_snapshot', 'notes', 'total', 'presented_at', 'accepted_at', 'rejected_at', 'document_reference', 'document_date', 'introduction', 'payment_terms', 'ai_instructions', 'price_note', 'issuer_snapshot'];

    protected $casts = ['client_snapshot' => 'array', 'issuer_snapshot' => 'array', 'document_date' => 'date', 'total' => 'decimal:2', 'presented_at' => 'datetime', 'accepted_at' => 'datetime', 'rejected_at' => 'datetime'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function previousQuote(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_quote_id');
    }

    public function nextQuote(): HasOne
    {
        return $this->hasOne(self::class, 'previous_quote_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopeCommercialOrder(Builder $query): Builder
    {
        return $query->orderByRaw('CASE WHEN quotes.status = ? THEN 1 ELSE 0 END', ['rejected'])
            ->orderByDesc('quotes.created_at')->orderByDesc('quotes.id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->isAdministration()) {
            return $query;
        }
        if ($user->isCommercial()) {
            return $query->where('quotes.status', '!=', 'draft')
                ->whereHas('client', fn ($client) => $client->where('commercial_user_id', $user->id));
        }

        return $query->whereRaw('1 = 0');
    }
}
