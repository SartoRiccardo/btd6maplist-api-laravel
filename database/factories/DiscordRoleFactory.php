<?php

namespace Database\Factories;

use App\Models\AchievementRole;
use App\Models\DiscordRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscordRole>
 */
class DiscordRoleFactory extends Factory
{
    protected $model = DiscordRole::class;

    public function definition(): array
    {
        return [
            'ar_lb_format' => 1,
            'ar_lb_type' => 'points',
            'ar_threshold' => fake()->numberBetween(1, 1000),
            'guild_id' => fake()->unique()->randomNumber(5, true) . str_repeat('0', 12),
            'role_id' => fake()->unique()->randomNumber(5, true) . str_repeat('0', 12),
        ];
    }

    public function forAchievement(AchievementRole $role): self
    {
        return $this->state(fn(array $attributes) => [
            'ar_lb_format' => $role->lb_format,
            'ar_lb_type' => $role->lb_type,
            'ar_threshold' => $role->threshold,
        ]);
    }
}
