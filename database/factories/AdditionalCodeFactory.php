<?php

namespace Database\Factories;

use App\Models\AdditionalCode;
use Database\Factories\MapFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdditionalCode>
 */
class AdditionalCodeFactory extends Factory
{
    protected $model = AdditionalCode::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->regexify('[A-Z]{6}'),
            'description' => fake()->sentence(),
            'belongs_to' => MapFactory::new()->create()->code,
        ];
    }
}
