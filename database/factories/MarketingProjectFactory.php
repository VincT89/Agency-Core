<?php

namespace Database\Factories;

use App\Models\MarketingProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketingProject>
 */
class MarketingProjectFactory extends Factory
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
            'status' => \App\Enums\Social\MarketingProjectStatus::Draft,
            'type' => \App\Enums\Social\MarketingProjectType::OneShot,
        ];
    }
}
