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
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
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
        $relation = $this->belongsTo(Project::class);
        if (auth()->user()?->isCommercial()) {
            $relation->withoutGlobalScope(\App\Models\Scopes\ProjectSupremacyScope::class)
                ->select(['projects.id', 'projects.name', 'projects.client_id']);
        }
        return $relation;
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
        if ($user->isCommercial()) {
            return $query->forCommercial($user);
        }

        if ($user->canAccessAllProjects() || $user->isMarketing()) {
            return $query;
        }

        return $query->whereHas('project.users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }

    public function scopeForCommercial(Builder $query, User $user): Builder
    {
        return $query->where(function ($visible) use ($user) {
            $visible->where('tasks.assigned_to', $user->id)
                ->orWhere(fn ($clients) => $clients->forClients(Client::where('commercial_user_id', $user->id)->select('id')));
        });
    }

    public function scopeForClients(Builder $query, $clientIds): Builder
    {
        return $query->where(function ($visible) use ($clientIds) {
            $projects = fn () => Project::withoutGlobalScopes()->whereIn('client_id', $clientIds)->select('id');
            $visible->whereIn('tasks.project_id', $projects())
                ->orWhereIn('tasks.ticket_id', Ticket::withoutGlobalScopes()->whereIn('client_id', $clientIds)->select('id'))
                ->orWhereIn('tasks.id', \Illuminate\Support\Facades\DB::table('shoots')->select('task_id')->whereNotNull('task_id')
                    ->where(fn ($shoots) => $shoots->whereIn('project_id', $projects())
                        ->orWhereIn('marketing_campaign_id', \Illuminate\Support\Facades\DB::table('marketing_campaigns')->whereIn('client_id', $clientIds)->select('id'))));
        });
    }

    public function commercialShoot()
    {
        return $this->hasOne(\App\Models\Shooting\Shoot::class, 'task_id')->withoutGlobalScopes()
            ->select(['id', 'task_id', 'project_id', 'marketing_campaign_id', 'title', 'client_notes', 'location']);
    }

    public function clientForDisplay(): ?Client
    {
        if ($this->project?->client) { return $this->project->client; }
        $clientId = $this->ticket_id ? Ticket::withoutGlobalScopes()->whereKey($this->ticket_id)->value('client_id') : null;
        if (!$clientId && $this->commercialShoot?->project_id) {
            $clientId = Project::withoutGlobalScopes()->whereKey($this->commercialShoot->project_id)->value('client_id');
        }
        if (!$clientId && $this->commercialShoot?->marketing_campaign_id) {
            $clientId = MarketingCampaign::whereKey($this->commercialShoot->marketing_campaign_id)->value('client_id');
        }
        return $clientId ? Client::select(['id', 'name'])->find($clientId) : null;
    }
}
