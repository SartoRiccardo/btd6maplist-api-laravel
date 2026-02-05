<?php

namespace Database\Factories;

use App\Models\MapAlias;
use Database\Factories\MapFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MapAlias>
 */
class MapAliasFactory extends Factory
{
    protected $model = MapAlias::class;

    public function definition(): array
    {
        return [
            'alias' => fake()->unique()->word(),
            'map_code' => MapFactory::new()->create()->code,
        ];
    }
}
