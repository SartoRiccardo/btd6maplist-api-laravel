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
            'code' => fake()->unique()->regexify('[A-Z]{6}'),
            'name' => fake()->randomElement(['Dark Path', 'Monkey Meadows', 'Tree Stump', 'Town Center', 'Cubism', 'Market Center', 'Underground', 'Covered Garden', 'Quarry', 'Quiet Street', 'Bloonarius Prime', 'Balance', 'Encrypted', 'Bazaar', 'Adora Temple', 'Spring Spring', 'KartsNDarts', 'Moon Landing', 'Haunted', 'Downstream', 'Firing Range', 'Crystal Ruins', 'Glade', 'Skates', 'High Finance', 'Cornfield', 'Wall Street', 'Rosalia', 'Resort', 'In The Loop', 'Castle Revenge', 'Dark Castle', 'Erosion', 'Tulip Festival', 'Candy Falls', 'Winter Park', 'Carved', 'Park Path', 'Alpine Run', 'Frozen Over', 'Pats Pond', 'Peyote', 'Geared', 'Spice Islands', 'Coveted', 'Tree Line', 'Frozen Over', 'Intermediate Maps', 'Advanced Maps', 'Expert Maps', 'Extreme Maps', 'Beginner Maps', 'Medium Maps', 'Hard Maps', 'Insane Maps']),
            'r6_start' => fake()->optional()->url(),
            'map_data' => fake()->optional()->url(),
            'map_preview_url' => 'https://data.ninjakiwi.com/btd6/maps/map/' . fake()->unique()->regexify('[A-Z]{6}') . '/preview',
            'map_notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate the map has metadata.
     */
    public function withMeta(array $overrides = []): static
    {
        return $this->afterCreating(function (Map $map) use ($overrides) {
            MapListMeta::factory()->create(array_merge([
                'code' => $map->code,
                'placement_curver' => null,
                'placement_allver' => null,
                'difficulty' => null,
                'botb_difficulty' => null,
                'remake_of' => null,
            ], $overrides));
        });
    }
}
