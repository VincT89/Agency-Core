<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\Social\MarketingCampaignPostStatus;
use App\Enums\Social\MarketingCampaignPostType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MarketingCampaignPost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'date',
        'ai_analysis_enabled' => 'boolean',
        'submitted_to_n8n_at' => 'datetime',
        'n8n_payload' => 'array',
        'status' => MarketingCampaignPostStatus::class,
        'content_type' => MarketingCampaignPostType::class,
        'generated_at' => 'datetime',
        'n8n_completed_at' => 'datetime',
        'sent_to_client_at' => 'datetime',
        'client_approved_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'marketing_campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MarketingCampaignPostVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(MarketingCampaignPostVersion::class, 'id', 'current_version_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(MarketingCampaignPostComment::class);
    }

    public function internalComments(): HasMany
    {
        return $this->comments()->where('visibility', 'internal');
    }

    public function clientComments(): HasMany
    {
        return $this->comments()->where('visibility', '!=', 'internal');
    }

    public function reviewTokens(): MorphMany
    {
        return $this->morphMany(ClientReviewToken::class, 'reviewable');
    }

    public function canRegenerate(): bool
    {
        return ! in_array($this->status, [
            MarketingCampaignPostStatus::Published,
            MarketingCampaignPostStatus::Cancelled,
        ], true);
    }

    public function getMediaUrlAttribute(): ?string
    {
        if (!$this->media_path) {
            return null;
        }
        
        return route('media.marketing-campaign-posts', [
            'path' => $this->media_path
        ]);
    }
}
