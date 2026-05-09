<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingCampaignPostComment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'visibility' => \App\Enums\Social\MarketingCampaignPostCommentVisibility::class,
        'type' => \App\Enums\Social\MarketingCampaignPostCommentType::class,
        'source' => \App\Enums\Social\CommentSource::class,
    ];

    public function post()
    {
        return $this->belongsTo(MarketingCampaignPost::class, 'marketing_campaign_post_id');
    }

    public function version()
    {
        return $this->belongsTo(MarketingCampaignPostVersion::class, 'marketing_campaign_post_version_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
