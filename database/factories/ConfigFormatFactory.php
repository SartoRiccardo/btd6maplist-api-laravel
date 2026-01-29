<?php

namespace Database\Factories;

use App\Models\ConfigFormat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConfigFormat>
 */
class ConfigFormatFactory extends Factory
{
    protected $model = ConfigFormat::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'config_name' => fake()->word(),
            'format_id' => fake()->numberBetween(1, 100),
        ];
    }
}
