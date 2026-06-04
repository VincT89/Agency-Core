<?php

namespace Database\Factories;

use App\Models\MarketingCampaignPostMedia;
use App\Models\MarketingCampaignPost;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingCampaignPostMediaFactory extends Factory
{
    protected $model = MarketingCampaignPostMedia::class;

    public function definition(): array
    {
        return [
            'marketing_campaign_post_id' => MarketingCampaignPost::factory(),
            'disk' => 'public',
            'path' => 'marketing/campaign-posts/test.mp4',
            'source' => 'local',
            'media_type' => 'video',
            'original_name' => 'test.mp4',
            'mime_type' => 'video/mp4',
        ];
    }
}
