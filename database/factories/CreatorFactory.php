<?php

namespace Database\Factories;

use App\Models\Creator;
use App\Models\Map;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Creator>
 */
class CreatorFactory extends Factory
{
    protected $model = Creator::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'map_code' => Map::factory(),
            'role' => fake()->randomElement(['Gameplay', 'Decoration', 'Both']),
        ];
    }
}
