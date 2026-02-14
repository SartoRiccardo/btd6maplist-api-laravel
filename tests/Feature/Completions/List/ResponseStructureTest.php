<?php

namespace Tests\Feature\Completions\List;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;

class ResponseStructureTest extends TestCase
{
    #[Group('get')]
    #[Group('completions')]
    #[Group('response')]
    public function test_returns_completions_with_default_parameters(): void
    {
        $count = 3;
        $completions = Completion::factory()->count($count)->create();
        $players = User::factory()->count(2)->create();
        $metas = CompletionMeta::factory()
            ->count($count)
            ->accepted()
            ->sequence(fn($seq) => [
                'completion_id' => $completions[$seq->index]->id,
                'created_on' => now()->subSeconds(10 - $seq->index),
            ])
            ->create();

        // Attach players to each meta
        foreach ($metas as $index => $meta) {
            $meta->players()->attach($players, ['run' => $meta->id]);
        }

        // Reload metas with relationships
        $metas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($completions, $metas);

        $actual = $this->getJson('/api/completions')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('response')]
    public function test_returns_empty_array_when_no_completions(): void
    {
        $actual = $this->getJson('/api/completions')
            ->assertStatus(200)
            ->json();

        $expected = [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => 0,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('response')]
    public function test_default_timestamp_is_now(): void
    {
        $now = now();

        $includedCompletions = Completion::factory()->count(2)->create();
        $includedMetas = CompletionMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'completion_id' => $includedCompletions[$seq->index]->id,
                'created_on' => $now->copy()->subHour(),
            ])
            ->create();

        // Future completion
        Completion::factory()->withMeta([
            'created_on' => $now->copy()->addHour(),
        ])->create();

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($includedCompletions, $includedMetas);

        $actual = $this->getJson('/api/completions')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('response')]
    public function test_include_map_metadata_parameter(): void
    {
        $count = 2;
        $completions = Completion::factory()->count($count)->create();
        $players = User::factory()->count(2)->create();
        $metas = CompletionMeta::factory()
            ->count($count)
            ->accepted()
            ->sequence(fn($seq) => [
                'completion_id' => $completions[$seq->index]->id,
                'created_on' => now()->subSeconds(10 - $seq->index),
            ])
            ->create();

        $map1 = Map::find($completions[0]->map_code);
        $map1Meta = MapListMeta::with('retroMap.game')
            ->where('code', $map1->code)
            ->first();

        $map2 = Map::find($completions[1]->map_code);
        $map2Meta = MapListMeta::with('retroMap.game')
            ->where('code', $map2->code)
            ->first();

        // Attach players to each meta
        foreach ($metas as $index => $meta) {
            $meta->players()->attach($players, ['run' => $meta->id]);
        }

        $actual = $this->getJson('/api/completions?include=map.metadata')
            ->assertStatus(200)
            ->json('data');

        $this->assertEquals([
            ...$map1->toArray(),
            ...$map1Meta->toArray(),
        ], $actual[0]['map']);

        $this->assertEquals([
            ...$map2->toArray(),
            ...$map2Meta->toArray(),
        ], $actual[1]['map']);
    }
}
