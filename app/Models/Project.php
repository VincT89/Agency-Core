<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable([
    'client_id',
    'name',
    'slug',
    'code',
    'description',
    'status',
    'start_date',
    'end_date',
    'notes',
])]
class Project extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\ProjectSupremacyScope);

        static::deleting(function ($project) {
            $project->tickets->each(fn($ticket) => $ticket->delete());
            $project->calendarEvents->each(fn($event) => $event->delete());
            $project->attachments->each(fn($attachment) => $attachment->delete());
        });
    }

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Attivo',
            'completed' => 'Completato',
            'on_hold' => 'In pausa',
            'cancelled' => 'Annullato',
            default => ucfirst((string) $this->status),
        };
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot([
                'role',
                'assignment_status',
                'assigned_at',
                'unassigned_at',
            ])
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
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

    public function marketingProjects(): HasMany
    {
        return $this->hasMany(MarketingProject::class);
    }
}
