<?php

namespace Database\Factories;

use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingCampaignPostFactory extends Factory
{
    protected $model = MarketingCampaignPost::class;

    public function definition(): array
    {
        return [
            'marketing_campaign_id' => MarketingCampaign::factory(),
            'created_by' => User::factory(),
            'title' => $this->faker->sentence,
            'status' => 'draft',
        ];
    }
}
