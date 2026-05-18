<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

#[Fillable([
    'project_id',
    'ticket_id',
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

        static::deleting(function ($task) {
            $task->attachments->each(fn($attachment) => $attachment->delete());
        });
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
            'waiting' => 'In attesa',
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

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
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

    public function expenses(): MorphMany
    {
        return $this->morphMany(Expense::class, 'expenseable');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('sort_order');
    }

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
        return $query->whereNotNull('due_date')
                     ->whereDate('due_date', '<', today())
                     ->whereNotIn('status', ['done', 'cancelled']);
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
