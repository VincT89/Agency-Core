<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAvailability>
 */
class UserAvailabilityFactory extends Factory
{
    protected $model = UserAvailability::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('today', '+30 days')->format('Y-m-d'),
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
        ];
    }
}
