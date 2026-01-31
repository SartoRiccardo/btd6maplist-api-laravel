<?php

namespace Database\Factories;

use App\Models\CompPlayer;
use App\Models\CompletionMeta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompPlayer>
 */
class CompPlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'run' => CompletionMeta::factory(),
        ];
    }
}
