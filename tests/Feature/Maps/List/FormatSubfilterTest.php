<?php

namespace Tests\Feature\Maps\List;

use App\Constants\FormatConstants;
use App\Models\Config;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\RetroGame;
use App\Models\RetroMap;
use Tests\Helpers\MapTestHelper;
use Tests\TestCase;

class FormatSubfilterTest extends TestCase
{
    #[Group('get')]
    #[Group('maps')]
    #[Group('format_subfilter')]
    public function test_expert_list_with_subfilter_filters_by_specific_difficulty(): void
    {
        $targetDifficulty = 2;
        $includedCount = 2;

        $maps = Map::factory()->count($includedCount + 2)->create();
        $metas = MapListMeta::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'difficulty' => $seq->index < $includedCount ? $targetDifficulty : $targetDifficulty + 1,
                'created_on' => now()->subHours($includedCount - $seq->index),
            ])
            ->create();
        Map::factory()->withMeta(['difficulty' => null])->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::EXPERT_LIST . "&format_subfilter={$targetDifficulty}")
            ->assertStatus(200)
            ->json();

        $includedMetas = $metas->take($includedCount)->values();

        $expected = [
            'data' => $maps->take($includedCount)
                ->zip($includedMetas)
                ->map(fn($pair) => MapTestHelper::mergeMapMeta($pair[0], $pair[1]))
                ->values()
                ->toArray(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => 2,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format_subfilter')]
    public function test_best_of_the_best_with_subfilter_filters_by_specific_botb_difficulty(): void
    {
        $targetBotbDifficulty = 4;
        $includedCount = 2;

        $maps = Map::factory()->count($includedCount + 2)->create();
        $metas = MapListMeta::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'botb_difficulty' => $seq->index < $includedCount ? $targetBotbDifficulty : $targetBotbDifficulty + 1,
                'created_on' => now()->subHours($maps->count() - $seq->index),
            ])
            ->create();
        Map::factory()->withMeta(['botb_difficulty' => null])->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::BEST_OF_THE_BEST . "&format_subfilter={$targetBotbDifficulty}")
            ->assertStatus(200)
            ->json();

        $includedMetas = $metas->take($includedCount)->values();

        $expected = [
            'data' => $maps->take($includedCount)
                ->zip($includedMetas)
                ->map(fn($pair) => MapTestHelper::mergeMapMeta($pair[0], $pair[1]))
                ->values()
                ->toArray(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => $includedCount,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format_subfilter')]
    public function test_nostalgia_pack_with_subfilter_filters_by_retro_game_game_id(): void
    {
        $targetGameId = 123;

        $retroGame1 = RetroGame::factory()->create(['game_id' => $targetGameId]);
        $retroGame2 = RetroGame::factory()->create();

        $includedMaps = Map::factory()->count(2)->create();
        $includedMetas = MapListMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'code' => $includedMaps[$seq->index]->code,
                'created_on' => now()->subHours($includedMaps->count() - $seq->index),
                'remake_of' => RetroMap::factory()->create([
                    'retro_game_id' => $retroGame1->id,
                ])->id,
            ])
            ->create();

        $excludedMaps = Map::factory()->count(2)->create();
        MapListMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'code' => $excludedMaps[$seq->index]->code,
                'remake_of' => RetroMap::factory()->create([
                    'retro_game_id' => $retroGame2->id,
                ])->id,
            ])
            ->create();
        Map::factory()->withMeta(['remake_of' => null])->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::NOSTALGIA_PACK . "&format_subfilter={$targetGameId}")
            ->assertStatus(200)
            ->json();

        $includedMetas->load('retroMap.game');

        $expected = [
            'data' => $includedMaps->zip($includedMetas)
                ->map(fn($pair) => MapTestHelper::mergeMapMeta($pair[0], $pair[1]))
                ->toArray(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => 2,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format_subfilter')]
    public function test_subfilter_without_format_id_is_ignored(): void
    {
        $count = 5;
        $maps = Map::factory()->count($count)->create();
        $metas = MapListMeta::factory()
            ->count($count)
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'created_on' => now()->subHours($maps->count() - $seq->index),
            ])
            ->create();

        $actual = $this->getJson('/api/maps?format_subfilter=2')
            ->assertStatus(200)
            ->json();

        $expected = [
            'data' => $maps->zip($metas)
                ->map(fn($pair) => MapTestHelper::mergeMapMeta($pair[0], $pair[1]))
                ->values()
                ->toArray(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => $count,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format_subfilter')]
    public function test_subfilter_with_maplist_format_is_ignored(): void
    {
        $count = 10;

        $maps = Map::factory()->count($count)->create();
        $metas = MapListMeta::factory()
            ->count($count)
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'placement_curver' => $seq->index + 1,
            ])
            ->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST . '&format_subfilter=999')
            ->assertStatus(200)
            ->json();

        $expected = [
            'data' => $maps->zip($metas)
                ->map(fn($pair) => MapTestHelper::mergeMapMeta($pair[0], $pair[1]))
                ->values()
                ->toArray(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => $count,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }
}
