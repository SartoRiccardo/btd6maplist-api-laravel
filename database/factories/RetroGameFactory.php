<?php

namespace Database\Factories;

use App\Models\RetroGame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RetroGame>
 */
class RetroGameFactory extends Factory
{
    protected $model = RetroGame::class;

    public function definition(): array
    {
        return [
            'game_id' => fake()->numberBetween(1, 10),
            'category_id' => fake()->numberBetween(1, 5),
            'subcategory_id' => fake()->numberBetween(1, 3),
            'game_name' => fake()->randomElement(['BTD6', 'BTD5', 'BTD4']),
            'category_name' => fake()->randomElement(['Beginner', 'Intermediate', 'Advanced', 'Expert']),
            'subcategory_name' => fake()->optional()->randomElement(['Easy', 'Medium', 'Hard']),
        ];
    }
}
