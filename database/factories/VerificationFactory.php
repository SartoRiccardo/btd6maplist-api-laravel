<?php

namespace Database\Factories;

use App\Models\Map;
use App\Models\Verification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Verification>
 */
class VerificationFactory extends Factory
{
    protected $model = Verification::class;

    public function definition(): array
    {
        return [
            'map_code' => Map::factory(),
            'user_id' => User::factory(),
            'version' => 441, // Current BTD6 version (44.1 * 10)
        ];
    }
}
