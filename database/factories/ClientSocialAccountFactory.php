<?php

namespace Database\Factories;

use App\Models\ClientSocialAccount;
use App\Models\Client;
use App\Enums\Social\SocialPlatform;
use App\Enums\Social\SocialApiStatus;
use App\Enums\Social\SocialConnectionStrategy;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientSocialAccountFactory extends Factory
{
    protected $model = ClientSocialAccount::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'platform' => SocialPlatform::Tiktok,
            'api_status' => SocialApiStatus::Connected,
            'connection_strategy' => SocialConnectionStrategy::PlatformOauth,
            'access_token' => $this->faker->md5,
            'account_name' => $this->faker->userName,
            'is_ready_to_publish' => true,
        ];
    }
}
