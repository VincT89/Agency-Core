<?php

namespace Database\Factories;

use App\Models\SocialPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialPost>
 */
class SocialPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => \App\Models\Project::factory(),
            'client_id' => \App\Models\Client::factory(),
            'title' => $this->faker->sentence,
            'status' => \App\Enums\Social\SocialPostStatus::Draft,
            'format' => '1080x1350',
            'source' => \App\Enums\Social\SocialPostSource::N8n,
        ];
    }
}
