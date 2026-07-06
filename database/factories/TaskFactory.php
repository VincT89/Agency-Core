<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'created_by' => \App\Models\User::factory(),
            'assigned_to' => \App\Models\User::factory(),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => 'todo',
            'priority' => 'medium',
        ];
    }
}
