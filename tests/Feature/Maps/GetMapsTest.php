<?php

namespace Tests\Feature\Maps;

use App\Models\Map;
use App\Models\Verification;
use PHPUnit\Attributes\Group;
use Tests\Helpers\MapTestHelper;
use Tests\TestCase;

class GetMapsTest extends TestCase
{
    #[Group('get')]
    public function test_get_maps_returns_format_1_by_default(): void
    {
        $map2 = Map::factory()->withMeta(['placement_curver' => 2])->create();
        $map1 = Map::factory()->withMeta(['placement_curver' => 1])->create();
        Map::factory()->withMeta(['placement_allver' => 1])->create();

        $actual = $this->getJson('/api/maps')
            ->assertStatus(200)
            ->json();

        $expected = [
            MapTestHelper::expectedMinimalMap($map1, 1, false),
            MapTestHelper::expectedMinimalMap($map2, 2, false),
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    public function test_get_maps_returns_correct_placement_field_for_format_1(): void
    {
        $map = Map::factory()->withMeta([
            'placement_curver' => 5,
            'placement_allver' => 15,
        ])->create();

        $actual = $this->getJson('/api/maps')
            ->assertStatus(200)
            ->json();

        $ourMaps = collect($actual)->filter(fn($m) => $m['code'] === $map->code)->values();
        $this->assertCount(1, $ourMaps);

        $expected = [MapTestHelper::expectedMinimalMap($map, 5, false)];
        $this->assertEquals($expected, $ourMaps->toArray());
    }

    #[Group('get')]
    public function test_get_maps_returns_correct_placement_field_for_format_2(): void
    {
        $map = Map::factory()->withMeta([
            'placement_curver' => 5,
            'placement_allver' => 15,
        ])->create();

        $actual = $this->getJson('/api/maps?format=2')
            ->assertStatus(200)
            ->json();

        $ourMaps = collect($actual)->filter(fn($m) => $m['code'] === $map->code)->values();
        $this->assertCount(1, $ourMaps);

        $expected = [MapTestHelper::expectedMinimalMap($map, 15, false)];
        $this->assertEquals($expected, $ourMaps->toArray());
    }

    #[Group('get')]
    public function test_get_maps_returns_verified_flag(): void
    {
        $map = Map::factory()->withMeta(['placement_curver' => 5])->create();
        Verification::factory()->for($map)->create();

        $actual = $this->getJson('/api/maps')
            ->assertStatus(200)
            ->json();

        $ourMaps = collect($actual)->filter(fn($m) => $m['code'] === $map->code)->values();
        $this->assertCount(1, $ourMaps);

        $expected = [MapTestHelper::expectedMinimalMap($map, 5, true)];
        $this->assertEquals($expected, $ourMaps->toArray());
    }

    #[Group('get')]
    public function test_get_maps_returns_unverified_flag(): void
    {
        $map = Map::factory()->withMeta(['placement_curver' => 5])->create();

        $actual = $this->getJson('/api/maps')
            ->assertStatus(200)
            ->json();

        $ourMaps = collect($actual)->filter(fn($m) => $m['code'] === $map->code)->values();
        $this->assertCount(1, $ourMaps);

        $expected = [MapTestHelper::expectedMinimalMap($map, 5, false)];
        $this->assertEquals($expected, $ourMaps->toArray());
    }

    #[Group('get')]
    public function test_get_maps_format_11_requires_filter(): void
    {
        $this->getJson('/api/maps?format=11')
            ->assertStatus(400)
            ->assertJson(['error' => 'Filter is required for this format']);
    }

    #[Group('get')]
    public function test_get_maps_returns_empty_for_invalid_format(): void
    {
        $this->getJson('/api/maps?format=999')
            ->assertStatus(400)
            ->assertJson(['error' => 'Invalid format']);
    }
}
