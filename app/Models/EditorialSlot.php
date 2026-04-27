<?php

namespace App\Models;

use App\Enums\Social\EditorialSlotStatus;
use App\Enums\Social\SocialPlatform;
use Illuminate\Database\Eloquent\Model;

class EditorialSlot extends Model
{
    protected $fillable = [
        'project_id',
        'social_post_id',
        'scheduled_at',
        'platform',
        'status',
        'notes',
        'created_by',
        'published_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => EditorialSlotStatus::class,
            'platform' => SocialPlatform::class,
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function post()
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
