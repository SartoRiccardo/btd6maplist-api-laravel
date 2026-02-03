<?php

namespace Database\Factories;

use App\Constants\FormatConstants;
use App\Models\CompPlayer;
use App\Models\CompletionMeta;
use App\Models\Completion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompletionMeta>
 */
class CompletionMetaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'completion_id' => Completion::factory(),
            'black_border' => fake()->boolean(),
            'no_geraldo' => fake()->boolean(),
            'lcc_id' => null,
            'created_on' => now(),
            'deleted_on' => null,
            'accepted_by_id' => null,
            'format_id' => fake()->randomElement([
                FormatConstants::MAPLIST,
                FormatConstants::MAPLIST_ALL_VERSIONS,
                FormatConstants::NOSTALGIA_PACK,
                FormatConstants::EXPERT_LIST,
                FormatConstants::BEST_OF_THE_BEST,
            ]),
            'copied_from_id' => null,
        ];
    }

    /**
     * Indicate the completion is accepted.
     */
    public function accepted(?int $acceptedBy = null): static
    {
        return $this->state(fn(array $attributes) => [
            'accepted_by_id' => $acceptedBy ?? fake()->randomNumber(9, true),
        ]);
    }

    /**
     * Indicate the completion is soft deleted.
     */
    public function deleted(): static
    {
        return $this->state(fn(array $attributes) => [
            'deleted_on' => now(),
        ]);
    }

    /**
     * Attach players to the completion metadata.
     */
    public function withPlayers(array $players): static
    {
        $this->players = $players;
        return $this->afterCreating(function (CompletionMeta $meta) use ($players) {
            foreach ($players as $player) {
                CompPlayer::factory()->create([
                    'run' => $meta->id,
                    'user_id' => is_object($player) ? $player->discord_id : $player,
                ]);
            }
        });
    }
}
