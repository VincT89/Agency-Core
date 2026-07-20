<?php

namespace Database\Factories;

use App\Models\ClientReviewToken;
use App\Models\MarketingCampaignPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ClientReviewTokenFactory extends Factory
{
    protected $model = ClientReviewToken::class;

    public function definition(): array
    {
        return [
            'reviewable_id' => MarketingCampaignPost::factory(),
            'reviewable_type' => MarketingCampaignPost::class,
            'token' => Str::random(32),
            'expires_at' => now()->addDays(7),
            'used_at' => null,
        ];
    }
    
    public function expired()
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }
    
    public function used()
    {
        return $this->state(fn (array $attributes) => [
            'used_at' => now()->subDay(),
        ]);
    }
}
