<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MarketingCampaignPostVersion extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'hashtags' => 'array',
        'image_urls' => 'array',
        'raw_payload' => 'array',
        'regeneration_type' => \App\Enums\Social\MarketingCampaignPostRegenerationType::class,
        'source' => \App\Enums\Social\MarketingCampaignPostVersionSource::class,
    ];

    public function post()
    {
        return $this->belongsTo(MarketingCampaignPost::class, 'marketing_campaign_post_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mediaItems(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            MarketingCampaignPostMedia::class,
            'marketing_campaign_post_version_media',
            'marketing_campaign_post_version_id',
            'marketing_campaign_post_media_id'
        )
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
