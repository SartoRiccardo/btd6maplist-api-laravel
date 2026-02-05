<?php

namespace Tests\Feature\Maps;

use App\Models\Map;
use PHPUnit\Attributes\Group;
use Tests\Helpers\MapTestHelper;
use Tests\TestCase;

class GetLegacyMapsTest extends TestCase
{
    #[Group('get')]
    public function test_get_legacy_maps_returns_pushed_off_maps(): void
    {
        // map_count config created by base seeder at 50
        $map = Map::factory()->withMeta(['placement_curver' => 55])->create();
        Map::factory()->withMeta(['placement_curver' => 10])->create();

        $actual = $this->getJson('/api/maps/legacy')
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $actual);
        $expected = [MapTestHelper::expectedMinimalMap($map, 55, false)];
        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    public function test_get_legacy_maps_returns_deleted_maps_with_null_placement(): void
    {
        $map = Map::factory()->withMeta()->create();

        $actual = $this->getJson('/api/maps/legacy')
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $actual);
        $expected = [MapTestHelper::expectedMinimalMap($map, null, false)];
        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    public function test_get_legacy_maps_orders_by_placement_nulls_last(): void
    {
        $map1 = Map::factory()->withMeta(['placement_curver' => 60])->create();
        $map2 = Map::factory()->withMeta(['placement_curver' => 55])->create();
        $map3 = Map::factory()->withMeta()->create();

        $actual = $this->getJson('/api/maps/legacy')
            ->assertStatus(200)
            ->json();

        // Filter to just our created maps
        $this->assertCount(3, $actual);
        $ourMaps = collect($actual)->filter(fn($m) => in_array($m['code'], [$map1->code, $map2->code, $map3->code]))->values();

        // Expected order: map2 (55), map1 (60), map3 (null)
        $expected = [
            MapTestHelper::expectedMinimalMap($map2, 55, false),
            MapTestHelper::expectedMinimalMap($map1, 60, false),
            MapTestHelper::expectedMinimalMap($map3, null, false),
        ];

        $this->assertEquals($expected, $ourMaps->toArray());
    }

    #[Group('get')]
    public function test_get_legacy_maps_includes_map_preview_url(): void
    {
        $map = Map::factory()->withMeta(['placement_curver' => 55])->create();

        $actual = $this->getJson('/api/maps/legacy')
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $actual);
        $expected = [MapTestHelper::expectedMinimalMap($map, 55, false)];
        $this->assertEquals($expected, $actual);
    }
}
