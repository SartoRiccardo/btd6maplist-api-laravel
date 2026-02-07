<?php

namespace Tests\Feature\Maps\List;

use App\Constants\FormatConstants;
use App\Models\Config;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\RetroMap;
use Tests\Helpers\MapTestHelper;
use Tests\TestCase;

class FormatFilterTest extends TestCase
{
    #[Group('get')]
    #[Group('maps')]
    #[Group('format')]
    public function test_maplist_format_filters_by_placement_curver(): void
    {
        $mapCount = Config::loadVars(['map_count'])->get('map_count', 50);

        $maps = Map::factory()->count($mapCount + 10)->create();
        $metas = MapListMeta::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'placement_curver' => $seq->index + 1,
            ])
            ->create();

        Map::factory()->count(10)->withMeta(['placement_curver' => null])->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json();

        $expected = MapTestHelper::expectedMapLists($maps->take($mapCount), $metas->take($mapCount));

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format')]
    public function test_maplist_all_versions_format_filters_by_placement_allver(): void
    {
        $mapCount = Config::loadVars(['map_count'])->get('map_count', 50);

        $maps = Map::factory()->count($mapCount + 10)->create();
        $metas = MapListMeta::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'placement_allver' => $seq->index + 1,
            ])
            ->create();

        Map::factory()->count(10)->withMeta(['placement_allver' => null])->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST_ALL_VERSIONS)
            ->assertStatus(200)
            ->json();

        $expected = MapTestHelper::expectedMapLists($maps->take($mapCount), $metas->take($mapCount));

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format')]
    public function test_expert_list_format_filters_by_difficulty_not_null(): void
    {
        $includedCount = 5;
        $excludedCount = 3;

        $includedMaps = Map::factory()->count($includedCount)->create();
        $includedMetas = MapListMeta::factory()
            ->count($includedCount)
            ->sequence(fn($seq) => [
                'code' => $includedMaps[$seq->index]->code,
                'difficulty' => $seq->index + 1,
            ])
            ->create();

        Map::factory()->count($excludedCount)->withMeta(['difficulty' => null])->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::EXPERT_LIST)
            ->assertStatus(200)
            ->json();

        $expected = MapTestHelper::expectedMapLists($includedMaps, $includedMetas->sortBy('difficulty')->values());

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format')]
    public function test_best_of_the_best_format_filters_by_botb_difficulty_not_null(): void
    {
        $includedCount = 5;
        $excludedCount = 3;

        $includedMaps = Map::factory()->count($includedCount)->create();
        $includedMetas = MapListMeta::factory()
            ->count($includedCount)
            ->sequence(fn($seq) => [
                'code' => $includedMaps[$seq->index]->code,
                'botb_difficulty' => $seq->index + 1,
            ])
            ->create();

        Map::factory()->count($excludedCount)->withMeta(['botb_difficulty' => null])->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::BEST_OF_THE_BEST)
            ->assertStatus(200)
            ->json();

        $expected = MapTestHelper::expectedMapLists($includedMaps, $includedMetas->sortBy('botb_difficulty')->values());

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format')]
    public function test_nostalgia_pack_format_filters_by_remake_of_and_includes_retro_map(): void
    {
        $includedCount = 3;
        $excludedCount = 2;

        $retroMaps = RetroMap::factory()->count($includedCount)->create();

        $includedMaps = Map::factory()->count($includedCount)->create();
        $includedMetas = MapListMeta::factory()
            ->count($includedCount)
            ->sequence(fn($seq) => [
                'code' => $includedMaps[$seq->index]->code,
                'remake_of' => $retroMaps[$seq->index]->id,
                'created_on' => now()->subHours($includedCount - $seq->index),
            ])
            ->create();
        $includedMetas->load('retroMap.game');

        Map::factory()->count($excludedCount)->withMeta(['remake_of' => null])->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::NOSTALGIA_PACK)
            ->assertStatus(200)
            ->json();


        $data = $includedMaps->zip($includedMetas)
            ->map(function ($pair) {
                [$map, $meta] = $pair;
                $merged = MapTestHelper::mergeMapMeta($map, $meta);
                $merged['retro_map'] = $meta->retroMap->toArray();
                $merged['retro_map']['game'] = $meta->retroMap->game->toArray();
                return $merged;
            })
            ->values()
            ->toArray();

        $expected = [
            'data' => $data,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => $includedCount,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }
}
