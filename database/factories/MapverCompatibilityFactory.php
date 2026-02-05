<?php

namespace Database\Factories;

use App\Models\MapverCompatibility;
use Database\Factories\MapFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MapverCompatibility>
 */
class MapverCompatibilityFactory extends Factory
{
    protected $model = MapverCompatibility::class;

    public function definition(): array
    {
        return [
            'map_code' => MapFactory::new()->create()->code,
            'status' => fake()->numberBetween(0, 2),
            'version' => fake()->numberBetween(400, 450),
        ];
    }
}
