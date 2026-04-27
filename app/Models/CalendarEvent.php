<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CalendarEvent extends Model
{
    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\ProjectSupremacyScope);
    }

    public const TYPES = [
        'internal_meeting',
        'client_meeting',
        'deadline',
        'review',
        'delivery',
        'other',
    ];

    public const STATUSES = [
        'scheduled',
        'completed',
        'cancelled',
    ];

    protected $fillable = [
        'client_id',
        'project_id',
        'created_by',
        'assigned_to',
        'title',
        'description',
        'type',
        'status',
        'start_at',
        'end_at',
        'is_all_day',
        'location',
        'meeting_provider',
        'meeting_url',
        'notes',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_all_day' => 'boolean',
    ];

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'Programmato',
            'completed' => 'Completato',
            'cancelled' => 'Annullato',
            default => ucfirst((string) $this->status),
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'internal_meeting' => 'Meeting Interno',
            'client_meeting' => 'Incontro Cliente',
            'deadline' => 'Scadenza',
            'review' => 'Revisione',
            'delivery' => 'Consegna',
            'other' => 'Altro',
            default => ucfirst((string) $this->type),
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
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
