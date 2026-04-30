<?php

namespace App\Models\Shooting;

use App\Models\Project;
use App\Models\User;
use App\Models\CalendarEvent;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\AuditLog;
use App\Models\MarketingProject;
use App\Enums\Shooting\ShootStatus;

class Shoot extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'marketing_project_id',
        'photographer_id',
        'created_by',
        'title',
        'code',
        'location',
        'status',
        'selected_slot_id',
        'client_confirmation_status',
        'client_confirmed_at',
        'client_confirmation_channel',
        'whatsapp_message_id',
        'calendar_event_id',
        'task_id',
        'internal_notes',
        'client_notes',
    ];

    protected $casts = [
        'status' => ShootStatus::class,
        'client_confirmed_at' => 'datetime',
    ];
    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\UserScopedShootScope);
    }
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function marketingProject(): BelongsTo
    {
        return $this->belongsTo(MarketingProject::class, 'marketing_project_id');
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function slots(): HasMany
    {
        return $this->hasMany(ShootSlot::class);
    }
    
    public function selectedSlot(): BelongsTo
    {
        return $this->belongsTo(ShootSlot::class, 'selected_slot_id');
    }
    
    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }
    
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
