<?php

namespace Tests\Feature\Completions\List;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\User;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;

class PlayerIdFilterTest extends TestCase
{
    #[Group('get')]
    #[Group('completions')]
    #[Group('player_id')]
    public function test_filter_by_non_existent_player_returns_error(): void
    {
        $this->getJson('/api/completions?player_id=999999999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('player_id');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('player_id')]
    public function test_filter_by_player_id_returns_only_completions_with_that_player(): void
    {
        $includedPlayer = User::factory()->create();
        $excludedPlayer = User::factory()->create();

        $includedCompletions = Completion::factory()->count(3)->create();
        $includedMetas = CompletionMeta::factory()
            ->count(3)
            ->sequence(fn($seq) => [
                'completion_id' => $includedCompletions[$seq->index]->id,
                'created_on' => now()->subSeconds(10 - $seq->index),
            ])
            ->create();

        foreach ($includedMetas as $meta) {
            $meta->players()->attach($includedPlayer, ['run' => $meta->id]);
        }

        // Excluded: completions with different player
        $excludedCompletions = Completion::factory()->count(2)->create();
        $excludedMetas = CompletionMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'completion_id' => $excludedCompletions[$seq->index]->id,
                'created_on' => now()->subSeconds(20 - $seq->index),
            ])
            ->create();

        foreach ($excludedMetas as $meta) {
            $meta->players()->attach($excludedPlayer, ['run' => $meta->id]);
        }

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($includedCompletions, $includedMetas);

        $actual = $this->getJson('/api/completions?player_id=' . $includedPlayer->discord_id)
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }
}
