<?php

namespace Database\Factories;

use App\Models\Map;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MapListMeta>
 */
class MapListMetaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fn() => Map::factory()->create()->code,
            'placement_curver' => null,
            'placement_allver' => null,
            'difficulty' => null,
            'optimal_heros' => [],
            'botb_difficulty' => null,
            'remake_of' => null,
            'created_on' => now(),
            'deleted_on' => null,
        ];
    }
}
