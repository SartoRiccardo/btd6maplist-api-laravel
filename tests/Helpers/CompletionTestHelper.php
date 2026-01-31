<?php

namespace Tests\Helpers;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\User;

class CompletionTestHelper
{
    /**
     * Build expected completion response structure for testing.
     *
     * @param CompletionMeta $meta The completion metadata
     * @param Completion $completion The completion
     * @param User $user The user who completed it
     * @param bool $currentLcc Whether this is the current LCC
     * @return array Expected structure matching the API response
     */
    public static function expectedCompletionResponse(
        CompletionMeta $meta,
        Completion $completion,
        User $user,
        bool $currentLcc
    ): array {
        return Completion::jsonStructure([
            ...$meta->toArray(),
            ...$completion->toArray(),
            'format' => $meta->format_id,
            'map' => Map::jsonStructure([
                ...$completion->map->latestMeta->toArray(),
                ...$completion->map->toArray(),
            ]),
            'users' => [
                ['id' => (string) $user->discord_id, 'name' => $user->name],
            ],
            'current_lcc' => $currentLcc,
        ]);
    }

    /**
     * Create multiple completions with metadata for testing.
     *
     * @param int $count Number of completions to create
     * @param User $player User to attach as player
     * @param bool $accepted Whether the completion should be accepted
     * @param bool $deleted Whether the completion should be soft deleted
     * @param int|null $formatId Optional format ID override (defaults to 1)
     * @return \Illuminate\Database\Eloquent\Collection<int, Completion>
     */
    public static function createCompletionsWithMeta(
        int $count,
        User $player,
        bool $accepted = false,
        bool $deleted = false,
        ?int $formatId = 1
    ) {
        $completions = Completion::factory()
            ->count($count)
            ->sequence(fn($seq) => ['submitted_on' => now()->subSeconds($count - $seq->index)])
            ->create();

        $metaFactory = CompletionMeta::factory()
            ->count($count)
            ->state(['format_id' => $formatId])
            ->sequence(fn($seq) => ['completion_id' => $completions[$seq->index]->id])
            ->withPlayers([$player]);

        if ($accepted) {
            $metaFactory = $metaFactory->accepted();
        }

        if ($deleted) {
            $metaFactory = $metaFactory->deleted();
        }

        $metaFactory->create();

        return $completions;
    }
}
