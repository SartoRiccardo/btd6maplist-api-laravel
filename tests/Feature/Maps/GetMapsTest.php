<?php

namespace Tests\Feature\Maps;

use Illuminate\Support\Arr;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\Verification;
use App\Models\RetroGame;
use App\Models\RetroMap;
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
    public function test_get_maps_format_11_filters_by_retro_game(): void
    {
        // Create retro games
        $game = RetroGame::factory()->create();

        $expectedCount = 2;
        $retroMaps = RetroMap::factory()->count($expectedCount)->for($game, 'game')->create();
        $otherRetroMaps = RetroMap::factory()->count(3)->create();
        $allMaps = [...$retroMaps, ...$otherRetroMaps];

        // Create BTD6 maps as remakes
        $maps = Map::factory()
            ->count(count($allMaps))
            ->create();
        MapListMeta::factory()
            ->count(count($allMaps))
            ->sequence(fn($seq) => [
                'remake_of' => $allMaps[$seq->index]->id,
                'code' => $maps[$seq->index]->code,
            ])
            ->create();

        $actual = $this->getJson('/api/maps?format=11&filter=' . $game->id)
            ->assertStatus(200)
            ->json();

        $this->assertCount($expectedCount, $actual);

        // Build expected and sort by retro_map.sort_order
        $expected = collect(range(0, $expectedCount - 1))
            ->map(fn($i) => [
                'sort_order' => $retroMaps[$i]->sort_order,
                'name' => $retroMaps[$i]->name,
                'code' => $maps[$i]->code,
                'placement' => [
                    'id' => $retroMaps[$i]->id,
                    'name' => $retroMaps[$i]->name,
                    'game_name' => $game->game_name,
                    'category_name' => $game->category_name,
                ],
                'is_verified' => false,
                'map_preview_url' => $maps[$i]->map_preview_url,
            ])
            ->sortBy('sort_order')
            ->map(fn($item) => Arr::except($item, 'sort_order'))
            ->values()
            ->toArray();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    public function test_get_maps_returns_empty_for_invalid_format(): void
    {
        $this->getJson('/api/maps?format=999')
            ->assertStatus(400)
            ->assertJson(['error' => 'Invalid format']);
    }
}
