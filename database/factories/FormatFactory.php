<?php

namespace Database\Factories;

use App\Models\Format;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Format>
 */
class FormatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->randomNumber(5, true),
            'name' => fake()->words(2, true),
            'hidden' => false,
            'run_submission_status' => fake()->randomElement([0, 1, 2]),
            'map_submission_status' => fake()->randomElement([0, 1, 2]),
            'map_submission_wh' => fake()->optional()->url(),
            'run_submission_wh' => fake()->optional()->url(),
            'emoji' => fake()->optional()->emoji(),
        ];
    }

    /**
     * Create a hidden format.
     */
    public function hidden(): self
    {
        return $this->state(fn (array $attributes) => [
            'hidden' => true,
        ]);
    }

    /**
     * Create a format with open submissions.
     */
    public function openSubmissions(): self
    {
        return $this->state(fn (array $attributes) => [
            'run_submission_status' => 1, // open
            'map_submission_status' => 1, // open
        ]);
    }
}
