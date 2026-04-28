<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPost extends Model
{
    protected $fillable = [
        'project_id',
        'marketing_project_id',
        'editorial_plan_id',
        'editorial_plan_slot_id',
        'client_id',
        'created_by',
        'external_id',
        'title',
        'status',
        'current_version_id',
        'format',
        'source',
        'sent_to_client_at',
        'client_approved_at',
        'client_rejected_at',
        'publication_mode',
        'scheduled_publish_at',
        'publication_status',
        'published_at',
        'published_by',
        'meta_post_id',
        'meta_permalink',
        'publication_error',
        'publication_attempts',
    ];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\Social\SocialPostStatus::class,
            'source' => \App\Enums\Social\SocialPostSource::class,
            'publication_mode' => \App\Enums\Social\PublicationMode::class,
            'publication_status' => \App\Enums\Social\PublicationStatus::class,
            'scheduled_publish_at' => 'datetime',
            'sent_to_client_at' => 'datetime',
            'client_approved_at' => 'datetime',
            'client_rejected_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function marketingProject()
    {
        return $this->belongsTo(MarketingProject::class);
    }

    public function editorialPlan()
    {
        return $this->belongsTo(EditorialPlan::class);
    }

    public function editorialPlanSlot()
    {
        return $this->belongsTo(EditorialPlanSlot::class);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function currentVersion()
    {
        return $this->belongsTo(SocialPostVersion::class, 'current_version_id');
    }

    public function versions()
    {
        return $this->hasMany(SocialPostVersion::class);
    }

    public function comments()
    {
        return $this->hasMany(SocialPostComment::class);
    }

    public function clientComments()
    {
        return $this->hasMany(SocialPostComment::class)
                    ->where('visibility', 'client')
                    ->orderBy('created_at', 'asc');
    }

    public function tokens()
    {
        return $this->hasMany(SocialPostReviewToken::class);
    }

    public function editorialSlots()
    {
        return $this->hasMany(EditorialSlot::class);
    }

    public function activeEditorialSlot()
    {
        return $this->hasOne(EditorialSlot::class)
                    ->whereIn('status', [
                        \App\Enums\Social\EditorialSlotStatus::Scheduled,
                        \App\Enums\Social\EditorialSlotStatus::Published,
                    ]);
    }

    public function isPlannable(): bool
    {
        return $this->status === \App\Enums\Social\SocialPostStatus::ClientApproved 
            && $this->activeEditorialSlot()->doesntExist();
    }
}
