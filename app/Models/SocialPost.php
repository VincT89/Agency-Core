<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPost extends Model
{
    protected $fillable = [
        'project_id',
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
    ];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\Social\SocialPostStatus::class,
            'source' => \App\Enums\Social\SocialPostSource::class,
            'sent_to_client_at' => 'datetime',
            'client_approved_at' => 'datetime',
            'client_rejected_at' => 'datetime',
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
