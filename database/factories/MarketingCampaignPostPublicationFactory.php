<?php

namespace Database\Factories;

use App\Models\MarketingCampaignPostPublication;
use App\Models\MarketingCampaignPost;
use App\Models\ClientSocialAccount;
use App\Enums\Social\PublicationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingCampaignPostPublicationFactory extends Factory
{
    protected $model = MarketingCampaignPostPublication::class;

    public function definition(): array
    {
        return [
            'marketing_campaign_post_id' => MarketingCampaignPost::factory(),
            'client_social_account_id' => ClientSocialAccount::factory(),
            'platform' => \App\Enums\Social\SocialPlatform::Tiktok->value,
            'status' => PublicationStatus::Pending->value,
            'external_post_id' => $this->faker->uuid,
        ];
    }
}
