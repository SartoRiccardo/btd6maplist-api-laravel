<?php

namespace Database\Factories;

use App\Models\RetroGame;
use App\Models\RetroMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RetroMap>
 */
class RetroMapFactory extends Factory
{
    protected $model = RetroMap::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Monkey Meadows', 'Dark Path', 'Tree Stump', 'Town Center', 'Cubism']),
            'sort_order' => fake()->numberBetween(1, 100),
            'preview_url' => fake()->url(),
            'retro_game_id' => RetroGame::factory(),
        ];
    }
}
