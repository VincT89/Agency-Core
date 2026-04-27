<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => $this->faker->company() . ' Project',
            'slug' => $this->faker->unique()->slug(),
            'code' => 'PRJ-' . $this->faker->unique()->numberBetween(1000, 9999),
            'description' => $this->faker->paragraph(),
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
        ];
    }
}
