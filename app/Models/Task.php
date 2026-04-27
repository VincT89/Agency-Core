<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'project_id',
    'created_by',
    'assigned_to',
    'title',
    'description',
    'status',
    'priority',
    'start_date',
    'due_date',
    'completed_at',
    'notes',
])]
class Task extends Model
{
    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\ProjectSupremacyScope);
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

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

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'todo' => 'Da fare',
            'in_progress' => 'In lavorazione',
            'review' => 'In revisione',
            'done' => 'Completata',
            'cancelled' => 'Annullata',
            default => ucfirst((string) $this->status),
        };
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

    // --- Scopes ---

    public function scopeAssignedTo($query, $userOrId)
    {
        $id = $userOrId instanceof User ? $userOrId->id : $userOrId;
        return $query->where('assigned_to', $id);
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['done', 'cancelled']);
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
