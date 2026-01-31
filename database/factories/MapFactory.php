<?php

namespace Database\Factories;

use App\Models\Map;
use App\Models\MapListMeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factory<\App\Models\Map>
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
            'r6_start' => fake()->url(),
            'map_data' => null,
            'map_preview_url' => null,
            'map_notes' => null,
        ];
    }

    /**
     * Indicate the map has metadata.
     */
    public function withMeta(): static
    {
        return $this->afterCreating(function (Map $map) {
            MapListMeta::factory()->create([
                'code' => $map->code,
            ]);
        });
    }
}
