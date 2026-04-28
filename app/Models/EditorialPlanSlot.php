<?php

namespace App\Models;

use App\Enums\Social\EditorialPlanSlotStatus;
use Illuminate\Database\Eloquent\Model;

class EditorialPlanSlot extends Model
{
    protected $fillable = [
        'editorial_plan_id',
        'social_post_id',
        'scheduled_date',
        'scheduled_time',
        'platforms',
        'topic',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => EditorialPlanSlotStatus::class,
            'scheduled_date' => 'date',
            'platforms' => 'array',
        ];
    }

    public function editorialPlan()
    {
        return $this->belongsTo(EditorialPlan::class);
    }

    public function socialPost()
    {
        return $this->belongsTo(SocialPost::class);
    }
}
