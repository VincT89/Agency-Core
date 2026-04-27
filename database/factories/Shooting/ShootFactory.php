<?php

namespace Database\Factories\Shooting;

use App\Models\Shooting\Shoot;
use App\Models\Project;
use App\Models\User;
use App\Enums\Shooting\ShootStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShootFactory extends Factory
{
    protected $model = Shoot::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'photographer_id' => User::factory(),
            'created_by' => User::factory(),
            'title' => $this->faker->sentence(3),
            'code' => 'SHT-' . strtoupper($this->faker->bothify('??####')),
            'location' => $this->faker->city(),
            'status' => ShootStatus::WaitingPhotographer,
            'internal_notes' => $this->faker->paragraph(),
            'client_notes' => $this->faker->paragraph(),
        ];
    }
}
