<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingCampaignPostVersion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'hashtags' => 'array',
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
}
