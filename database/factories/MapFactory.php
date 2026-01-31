<?php

namespace Database\Factories;

use App\Models\Map;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Map>
 */
class MapFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'ML' . fake()->unique()->bothify('??????'),
            'name' => fake()->sentence(),
            'r6_start' => fake()->dateTime(),
            'map_data' => null,
            'map_preview_url' => null,
            'map_notes' => null,
        ];
    }
}
