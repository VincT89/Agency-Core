<?php

namespace Database\Factories;

use App\Models\MarketingCampaign;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingCampaignFactory extends Factory
{
    protected $model = MarketingCampaign::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'created_by' => User::factory(),
            'name' => $this->faker->sentence,
            'status' => 'active',
        ];
    }
}
