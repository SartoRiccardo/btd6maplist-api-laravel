<?php

namespace Tests\Unit;

use App\Models\Map;
use App\Models\MapListMeta;
use App\Services\MapService;
use Carbon\Carbon;
use Tests\TestCase;

class MapServiceTest extends TestCase
{
    private MapService $mapService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapService = new MapService();
    }

    public function test_rerank_placements_does_nothing_when_all_positions_are_null(): void
    {
        $now = Carbon::now();

        $maps = Map::factory()->count(2)->create();
        MapListMeta::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'placement_curver' => $seq->index + 1,
                'placement_allver' => null,
                'created_on' => $now,
            ])
            ->create();

        $initialCount = MapListMeta::count();

        $this->mapService->rerankPlacements(
            curPositionFrom: null,
            curPositionTo: null,
            allPositionFrom: null,
            allPositionTo: null,
            ignoreCode: 'IGNORED',
            now: $now->copy()->addSecond()
        );

        $after = $now->copy()->addSecond();

        $this->assertEquals(1, MapListMeta::activeAtTimestamp($after)->where('code', $maps[0]->code)->first()->placement_curver);
        $this->assertEquals(2, MapListMeta::activeAtTimestamp($after)->where('code', $maps[1]->code)->first()->placement_curver);
        $this->assertEquals($initialCount, MapListMeta::count(), 'No new records should be created');
    }

    public function test_rerank_placements_brings_maps_below_down_by_one_when_moving_from_valid_to_null(): void
    {
        $now = Carbon::now();

        $maps = Map::factory()->count(3)->create();
        MapListMeta::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'placement_curver' => $seq->index + 3,
                'created_on' => $now->copy()->subMinute(),
            ])
            ->create();

        $this->mapService->rerankPlacements(
            curPositionFrom: 3,
            curPositionTo: null,
            allPositionFrom: null,
            allPositionTo: null,
            ignoreCode: $maps[0]->code,
            now: $now->copy()->addSecond()
        );

        $after = $now->copy()->addSecond();

        $this->assertEquals(3, MapListMeta::activeAtTimestamp($after)->where('code', $maps[1]->code)->first()->placement_curver);
        $this->assertEquals(4, MapListMeta::activeAtTimestamp($after)->where('code', $maps[2]->code)->first()->placement_curver);
    }

    public function test_rerank_placements_brings_maps_below_up_by_one_when_moving_from_null_to_valid(): void
    {
        $now = Carbon::now();

        $maps = Map::factory()->count(3)->create();
        MapListMeta::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'placement_curver' => $seq->index + 3,
                'created_on' => $now,
            ])
            ->create();

        $this->mapService->rerankPlacements(
            curPositionFrom: null,
            curPositionTo: 3,
            allPositionFrom: null,
            allPositionTo: null,
            ignoreCode: 'NEWMAP',
            now: $now->copy()->addSecond()
        );

        $after = $now->copy()->addSecond();

        $this->assertEquals(4, MapListMeta::activeAtTimestamp($after)->where('code', $maps[0]->code)->first()->placement_curver);
        $this->assertEquals(5, MapListMeta::activeAtTimestamp($after)->where('code', $maps[1]->code)->first()->placement_curver);
        $this->assertEquals(6, MapListMeta::activeAtTimestamp($after)->where('code', $maps[2]->code)->first()->placement_curver);
    }

    public function test_rerank_placements_moving_from_higher_to_lower_brings_maps_between_down_one_and_does_not_touch_others(): void
    {
        $now = Carbon::now();

        $maps = Map::factory()->count(5)->create();
        MapListMeta::factory()
            ->count($maps->count())
            ->sequence(
                ['code' => $maps[0]->code, 'placement_curver' => 1, 'created_on' => $now],
                ['code' => $maps[1]->code, 'placement_curver' => 3, 'created_on' => $now],  // This map just moved & we need to rerank
                ['code' => $maps[2]->code, 'placement_curver' => 3, 'created_on' => $now],
                ['code' => $maps[3]->code, 'placement_curver' => 6, 'created_on' => $now],
                ['code' => $maps[4]->code, 'placement_curver' => 9, 'created_on' => $now],
            )
            ->create();

        $this->mapService->rerankPlacements(
            curPositionFrom: 7,
            curPositionTo: 3,
            allPositionFrom: null,
            allPositionTo: null,
            ignoreCode: $maps[1]->code,
            now: $now->copy()->addSecond()
        );

        $after = $now->copy()->addSecond();

        $this->assertEquals(1, MapListMeta::activeAtTimestamp($after)->where('code', $maps[0]->code)->first()->placement_curver);
        $this->assertEquals(3, MapListMeta::activeAtTimestamp($after)->where('code', $maps[1]->code)->first()->placement_curver);
        $this->assertEquals(4, MapListMeta::activeAtTimestamp($after)->where('code', $maps[2]->code)->first()->placement_curver);
        $this->assertEquals(7, MapListMeta::activeAtTimestamp($after)->where('code', $maps[3]->code)->first()->placement_curver);
        $this->assertEquals(9, MapListMeta::activeAtTimestamp($after)->where('code', $maps[4]->code)->first()->placement_curver);
    }

    public function test_rerank_placements_moving_from_lower_to_higher_brings_maps_between_up_one_and_does_not_touch_others(): void
    {
        $now = Carbon::now();

        $maps = Map::factory()->count(5)->create();
        MapListMeta::factory()
            ->count($maps->count())
            ->sequence(
                ['code' => $maps[0]->code, 'placement_curver' => 1, 'created_on' => $now],
                ['code' => $maps[1]->code, 'placement_curver' => 4, 'created_on' => $now],
                ['code' => $maps[2]->code, 'placement_curver' => 5, 'created_on' => $now],
                ['code' => $maps[3]->code, 'placement_curver' => 7, 'created_on' => $now],  // This map just moved & we need to rerank
                ['code' => $maps[4]->code, 'placement_curver' => 7, 'created_on' => $now],
            )
            ->create();

        $this->mapService->rerankPlacements(
            curPositionFrom: 3,
            curPositionTo: 7,
            allPositionFrom: null,
            allPositionTo: null,
            ignoreCode: $maps[3]->code,
            now: $now->copy()->addSecond()
        );

        $after = $now->copy()->addSecond();

        $this->assertEquals(1, MapListMeta::activeAtTimestamp($after)->where('code', $maps[0]->code)->first()->placement_curver);
        $this->assertEquals(3, MapListMeta::activeAtTimestamp($after)->where('code', $maps[1]->code)->first()->placement_curver);
        $this->assertEquals(4, MapListMeta::activeAtTimestamp($after)->where('code', $maps[2]->code)->first()->placement_curver);
        $this->assertEquals(7, MapListMeta::activeAtTimestamp($after)->where('code', $maps[3]->code)->first()->placement_curver);
        $this->assertEquals(6, MapListMeta::activeAtTimestamp($after)->where('code', $maps[4]->code)->first()->placement_curver);
    }
}
