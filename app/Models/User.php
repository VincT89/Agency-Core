<?php

namespace App\Models;


use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\UserRole;

#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'status',
    'primary_specialization',
    'role',
    'password_changed_at',
    'last_tickets_viewed_at',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    // Trait per factory e notifiche
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'last_tickets_viewed_at' => 'datetime',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Attivo',
            'inactive' => 'Inattivo (sospeso)',
            default => ucfirst((string) $this->status),
        };
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot(['role', 'assignment_status', 'joined_at'])
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withPivot([
                'role',
                'assignment_status',
                'assigned_at',
                'unassigned_at',
            ])
            ->withTimestamps();
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function createdTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'created_by');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function createdCalendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'created_by');
    }

    public function assignedCalendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'assigned_to');
    }

    public function createdInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }

    public function createdPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'created_by');
    }

    public function dailyNotes(): HasMany
    {
        return $this->hasMany(UserDailyNote::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function canManageSystem(): bool
    {
        return $this->isAdmin();
    }

    public function isAdministration(): bool
    {
        return $this->role === UserRole::Administration;
    }

    public function isDeveloper(): bool
    {
        return $this->role === UserRole::Developer;
    }

    public function isMarketing(): bool
    {
        return $this->role === UserRole::Marketing;
    }

    public function isPhotographer(): bool
    {
        return $this->role === UserRole::Photographer;
    }

    public function isGraphicDesigner(): bool
    {
        return $this->role === UserRole::GraphicDesigner;
    }

    public function canAccessFinance(): bool
    {
        return $this->isAdmin() || $this->isAdministration();
    }

    public function canViewAuditLogs(): bool
    {
        return $this->isAdmin() || $this->isAdministration();
    }

    public function canBypassProjectScope(): bool
    {
        // Garantisce visibilità globale a profili amministrativi
        return $this->isAdmin() || $this->isAdministration();
    }

    public function hasOperationalDashboard(): bool
    {
        return $this->isOperationalStaff();
    }

    public function isOperationalStaff(): bool
    {
        return in_array($this->role, [
            UserRole::Developer,
            UserRole::Marketing,
            UserRole::Photographer,
            UserRole::GraphicDesigner,
        ]);
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }
}
