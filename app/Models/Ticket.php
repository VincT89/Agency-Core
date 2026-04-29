<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Ticket extends Model
{
    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\ProjectSupremacyScope);
    }

    public const TYPES = [
        'support',
        'bug',
        'request',
        'change',
        'admin',
    ];

    public const STATUSES = [
        'open',
        'in_progress',
        'waiting',
        'resolved',
        'closed',
    ];

    public const PRIORITIES = [
        'low',
        'medium',
        'high',
        'urgent',
    ];

    protected $fillable = [
        'client_id',
        'project_id',
        'created_by',
        'assigned_to',
        'code',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'opened_at',
        'due_date',
        'closed_at',
        'resolution_notes',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'due_date' => 'date',
        'closed_at' => 'datetime',
    ];

        public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'Bassa',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente',
            default => ucfirst((string) $this->priority),
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'support' => 'Supporto',
            'bug' => 'Bug',
            'request' => 'Richiesta',
            'change' => 'Modifica',
            'admin' => 'Amministrazione commerciale',
            default => ucfirst((string) $this->type),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Aperto',
            'in_progress' => 'In lavorazione',
            'waiting' => 'In attesa',
            'resolved' => 'Risolto',
            'closed' => 'Chiuso',
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



    public function scopeAssignedTo($query, $userOrId)
    {
        $id = $userOrId instanceof User ? $userOrId->id : $userOrId;
        return $query->where('assigned_to', $id);
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['resolved', 'closed']);
    }

    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->whereNotNull('due_date')
                     ->whereDate('due_date', '>=', today())
                     ->whereDate('due_date', '<=', today()->addDays($days));
    }

    public function scopeOverdue($query)
    {
        $today = today();
        return $query->whereNotNull('due_date')
                     ->whereDate('due_date', '<', $today);
    }
}
