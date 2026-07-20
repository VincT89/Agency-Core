<?php

namespace Database\Factories;

use App\Enums\Social\MarketingCampaignPostRegenerationType;
use App\Enums\Social\MarketingCampaignPostVersionSource;
use App\Models\MarketingCampaignPost;
use App\Models\MarketingCampaignPostVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingCampaignPostVersionFactory extends Factory
{
    protected $model = MarketingCampaignPostVersion::class;

    public function definition(): array
    {
        return [
            'marketing_campaign_post_id' => MarketingCampaignPost::factory(),
            'version_number' => 1,
            'regeneration_type' => MarketingCampaignPostRegenerationType::Full->value,
            'source' => MarketingCampaignPostVersionSource::N8n->value,
            'title' => $this->faker->sentence(),
            'caption' => $this->faker->paragraph(),
            'hashtags' => ['#test', '#social'],
            'image_url' => $this->faker->imageUrl(),
            'image_path' => null,
            'external_generation_id' => $this->faker->unique()->uuid(),
            'raw_payload' => ['foo' => 'bar'],
        ];
    }

    public function withMedia($media, array $pivotData = [])
    {
        return $this->afterCreating(function (MarketingCampaignPostVersion $version) use ($media, $pivotData) {
            $version->mediaItems()->attach($media, array_merge(['sort_order' => 0], $pivotData));
        });
    }
}
