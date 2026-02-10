<?php

namespace Tests\Feature\Completions\List;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\LeastCostChimps;
use App\Models\Map;
use App\Models\User;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;

class CombinedFiltersTest extends TestCase
{
    #[Group('get')]
    #[Group('completions')]
    #[Group('combined')]
    public function test_map_code_and_player_id_filters(): void
    {
        $player1 = User::factory()->create();
        $player2 = User::factory()->create();

        $map1 = Map::factory()->withMeta()->create();
        $map2 = Map::factory()->withMeta()->create();

        // Included: map1 with player1
        $includedCompletions = Completion::factory()->count(2)
            ->sequence(fn($seq) => ['map_code' => $map1->code])
            ->create();

        $includedMetas = CompletionMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'completion_id' => $includedCompletions[$seq->index]->id,
                'created_on' => now()->subSeconds(10 - $seq->index),
            ])
            ->create();

        foreach ($includedMetas as $meta) {
            $meta->players()->attach($player1, ['run' => $meta->id]);
        }

        // Excluded: map1 with player2
        $excludedCompletions1 = Completion::factory()->count(1)
            ->sequence(fn($seq) => ['map_code' => $map1->code])
            ->create();

        $excludedMetas1 = CompletionMeta::factory()
            ->count(1)
            ->sequence(fn($seq) => [
                'completion_id' => $excludedCompletions1[$seq->index]->id,
                'created_on' => now()->subSeconds(5),
            ])
            ->create();

        foreach ($excludedMetas1 as $meta) {
            $meta->players()->attach($player2, ['run' => $meta->id]);
        }

        // Excluded: map2 with player1
        $excludedCompletions2 = Completion::factory()->count(1)
            ->sequence(fn($seq) => ['map_code' => $map2->code])
            ->create();

        $excludedMetas2 = CompletionMeta::factory()
            ->count(1)
            ->sequence(fn($seq) => [
                'completion_id' => $excludedCompletions2[$seq->index]->id,
                'created_on' => now()->subSeconds(3),
            ])
            ->create();

        foreach ($excludedMetas2 as $meta) {
            $meta->players()->attach($player1, ['run' => $meta->id]);
        }

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($includedCompletions, $includedMetas);

        $actual = $this->getJson('/api/completions?map_code=' . $map1->code . '&player_id=' . $player1->discord_id)
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('combined')]
    public function test_lcc_and_black_border_filters(): void
    {
        $player = User::factory()->create();
        $lcc = LeastCostChimps::factory()->create();

        // Included: LCC and black_border
        $includedCompletions = Completion::factory()->count(2)->create();
        $includedMetas = CompletionMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'completion_id' => $includedCompletions[$seq->index]->id,
                'lcc_id' => $lcc->id,
                'black_border' => true,
                'created_on' => now()->subSeconds(10 - $seq->index),
            ])
            ->create();

        foreach ($includedMetas as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        // Excluded: LCC but no black_border
        $excludedCompletions1 = Completion::factory()->count(1)->create();
        $excludedMetas1 = CompletionMeta::factory()
            ->count(1)
            ->sequence(fn($seq) => [
                'completion_id' => $excludedCompletions1[$seq->index]->id,
                'lcc_id' => $lcc->id,
                'black_border' => false,
                'created_on' => now()->subSeconds(8),
            ])
            ->create();

        foreach ($excludedMetas1 as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        // Excluded: black_border but no LCC
        $excludedCompletions2 = Completion::factory()->count(1)->create();
        $excludedMetas2 = CompletionMeta::factory()
            ->count(1)
            ->sequence(fn($seq) => [
                'completion_id' => $excludedCompletions2[$seq->index]->id,
                'lcc_id' => null,
                'black_border' => true,
                'created_on' => now()->subSeconds(6),
            ])
            ->create();

        foreach ($excludedMetas2 as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($includedCompletions, $includedMetas);

        $actual = $this->getJson('/api/completions?lcc=only&black_border=only')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('combined')]
    public function test_lcc_and_pending_filters(): void
    {
        $player = User::factory()->create();
        $lcc = LeastCostChimps::factory()->create();

        // Included: LCC and pending
        $includedCompletions = Completion::factory()->count(2)->create();
        $includedMetas = CompletionMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'completion_id' => $includedCompletions[$seq->index]->id,
                'lcc_id' => $lcc->id,
                'accepted_by_id' => null,
                'created_on' => now()->subSeconds(10 - $seq->index),
            ])
            ->create();

        foreach ($includedMetas as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        // Excluded: LCC but accepted
        $excludedCompletions1 = Completion::factory()->count(1)->create();
        $excludedMetas1 = CompletionMeta::factory()
            ->count(1)
            ->sequence(fn($seq) => [
                'completion_id' => $excludedCompletions1[$seq->index]->id,
                'lcc_id' => $lcc->id,
                'accepted_by_id' => User::factory(),
                'created_on' => now()->subSeconds(8),
            ])
            ->create();

        foreach ($excludedMetas1 as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        // Excluded: pending but no LCC
        $excludedCompletions2 = Completion::factory()->count(1)->create();
        $excludedMetas2 = CompletionMeta::factory()
            ->count(1)
            ->sequence(fn($seq) => [
                'completion_id' => $excludedCompletions2[$seq->index]->id,
                'lcc_id' => null,
                'accepted_by_id' => null,
                'created_on' => now()->subSeconds(6),
            ])
            ->create();

        foreach ($excludedMetas2 as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($includedCompletions, $includedMetas);

        $actual = $this->getJson('/api/completions?lcc=only&pending=only')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('combined')]
    public function test_map_code_and_pending_filters(): void
    {
        $player = User::factory()->create();
        $map1 = Map::factory()->withMeta()->create();
        $map2 = Map::factory()->withMeta()->create();

        // Included: map1 and pending
        $includedCompletions = Completion::factory()->count(2)
            ->sequence(fn($seq) => ['map_code' => $map1->code])
            ->create();

        $includedMetas = CompletionMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'completion_id' => $includedCompletions[$seq->index]->id,
                'accepted_by_id' => null,
                'created_on' => now()->subSeconds(10 - $seq->index),
            ])
            ->create();

        foreach ($includedMetas as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        // Excluded: map1 but accepted
        $excludedCompletions1 = Completion::factory()->count(1)
            ->sequence(fn($seq) => ['map_code' => $map1->code])
            ->create();

        $excludedMetas1 = CompletionMeta::factory()
            ->count(1)
            ->sequence(fn($seq) => [
                'completion_id' => $excludedCompletions1[$seq->index]->id,
                'accepted_by_id' => User::factory(),
                'created_on' => now()->subSeconds(5),
            ])
            ->create();

        foreach ($excludedMetas1 as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        // Excluded: pending but different map
        $excludedCompletions2 = Completion::factory()->count(1)
            ->sequence(fn($seq) => ['map_code' => $map2->code])
            ->create();

        $excludedMetas2 = CompletionMeta::factory()
            ->count(1)
            ->sequence(fn($seq) => [
                'completion_id' => $excludedCompletions2[$seq->index]->id,
                'accepted_by_id' => null,
                'created_on' => now()->subSeconds(3),
            ])
            ->create();

        foreach ($excludedMetas2 as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($includedCompletions, $includedMetas);

        $actual = $this->getJson('/api/completions?map_code=' . $map1->code . '&pending=only')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('combined')]
    public function test_player_id_and_pending_filters(): void
    {
        $player1 = User::factory()->create();
        $player2 = User::factory()->create();

        // Included: player1 and pending
        $includedCompletions = Completion::factory()->count(2)->create();
        $includedMetas = CompletionMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'completion_id' => $includedCompletions[$seq->index]->id,
                'accepted_by_id' => null,
                'created_on' => now()->subSeconds(10 - $seq->index),
            ])
            ->create();

        foreach ($includedMetas as $meta) {
            $meta->players()->attach($player1, ['run' => $meta->id]);
        }

        // Excluded: player1 but accepted
        $excludedCompletions1 = Completion::factory()->count(1)->create();
        $excludedMetas1 = CompletionMeta::factory()
            ->count(1)
            ->sequence(fn($seq) => [
                'completion_id' => $excludedCompletions1[$seq->index]->id,
                'accepted_by_id' => User::factory(),
                'created_on' => now()->subSeconds(5),
            ])
            ->create();

        foreach ($excludedMetas1 as $meta) {
            $meta->players()->attach($player1, ['run' => $meta->id]);
        }

        // Excluded: pending but different player
        $excludedCompletions2 = Completion::factory()->count(1)->create();
        $excludedMetas2 = CompletionMeta::factory()
            ->count(1)
            ->sequence(fn($seq) => [
                'completion_id' => $excludedCompletions2[$seq->index]->id,
                'accepted_by_id' => null,
                'created_on' => now()->subSeconds(3),
            ])
            ->create();

        foreach ($excludedMetas2 as $meta) {
            $meta->players()->attach($player2, ['run' => $meta->id]);
        }

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($includedCompletions, $includedMetas);

        $actual = $this->getJson('/api/completions?player_id=' . $player1->discord_id . '&pending=only')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }
}
