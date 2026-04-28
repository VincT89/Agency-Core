<?php

namespace App\Models;

use App\Enums\Social\EditorialPlanStatus;
use Illuminate\Database\Eloquent\Model;

class EditorialPlan extends Model
{
    protected $fillable = [
        'marketing_project_id',
        'duration_days',
        'start_date',
        'end_date',
        'post_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => EditorialPlanStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function marketingProject()
    {
        return $this->belongsTo(MarketingProject::class);
    }

    public function slots()
    {
        return $this->hasMany(EditorialPlanSlot::class);
    }

    public function socialPosts()
    {
        return $this->hasMany(SocialPost::class);
    }
}
