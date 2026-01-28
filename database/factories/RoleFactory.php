<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
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
            'assign_on_create' => false,
            'internal' => false,
        ];
    }

    /**
     * Create a role that is assigned to new users on creation.
     */
    public function assignOnCreate(): self
    {
        return $this->state(fn(array $attributes) => [
            'assign_on_create' => true,
        ]);
    }
}
