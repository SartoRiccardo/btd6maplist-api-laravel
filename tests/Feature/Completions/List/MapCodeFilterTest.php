<?php

namespace Tests\Feature\Completions\List;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\User;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;

class MapCodeFilterTest extends TestCase
{
    #[Group('get')]
    #[Group('completions')]
    #[Group('map_code')]
    public function test_filter_by_non_existent_map_returns_error(): void
    {
        $this->getJson('/api/completions?map_code=NONEXISTENT')
            ->assertStatus(422)
            ->assertJsonValidationErrors('map_code');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('map_code')]
    public function test_filter_by_map_code_returns_only_completions_for_that_map(): void
    {
        $player = User::factory()->create();

        // Create the included map and completions
        $includedMap = Map::factory()->withMeta()->create();
        $includedCompletions = Completion::factory()->count(3)
            ->sequence(fn($seq) => ['map_code' => $includedMap->code])
            ->create();

        $includedMetas = CompletionMeta::factory()
            ->count(3)
            ->sequence(fn($seq) => [
                'completion_id' => $includedCompletions[$seq->index]->id,
                'created_on' => now()->subSeconds(10 - $seq->index),
            ])
            ->create();

        foreach ($includedMetas as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        // Excluded: completions for different map
        $excludedMap = Map::factory()->withMeta()->create();
        $excludedCompletions = Completion::factory()->count(2)
            ->sequence(fn($seq) => ['map_code' => $excludedMap->code])
            ->create();

        $excludedMetas = CompletionMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'completion_id' => $excludedCompletions[$seq->index]->id,
                'created_on' => now()->subSeconds(20 - $seq->index),
            ])
            ->create();

        foreach ($excludedMetas as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($includedCompletions, $includedMetas);

        $actual = $this->getJson('/api/completions?map_code=' . $includedMap->code)
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }
}
