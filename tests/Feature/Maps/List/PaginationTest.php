<?php

namespace Tests\Feature\Maps\List;

use App\Constants\FormatConstants;
use App\Models\Map;
use App\Models\MapListMeta;
use Tests\Helpers\MapTestHelper;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    #[Group('get')]
    #[Group('maps')]
    #[Group('pagination')]
    public function test_returns_maps_with_default_pagination(): void
    {
        $count = 15;
        $maps = Map::factory()->count($count)->create();
        $metas = MapListMeta::factory()
            ->count($count)
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'placement_curver' => $seq->index + 1,
            ])
            ->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST)
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
    #[Group('pagination')]
    public function test_returns_maps_with_custom_pagination(): void
    {
        $total = 25;
        $page = 2;
        $perPage = 10;

        $maps = Map::factory()->count($total)->create();
        $metas = MapListMeta::factory()
            ->count($total)
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'placement_curver' => $seq->index + 1,
            ])
            ->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST . "&page={$page}&per_page={$perPage}")
            ->assertStatus(200)
            ->json();

        $pageMaps = $maps->forPage($page, $perPage);
        $metasByKey = $metas->keyBy('code');
        $pageMetas = $pageMaps->map(fn($map) => $metasByKey->get($map->code))->values();

        $expected = [
            'data' => $pageMaps->zip($pageMetas)
                ->map(fn($pair) => MapTestHelper::mergeMapMeta($pair[0], $pair[1]))
                ->values()
                ->toArray(),
            'meta' => [
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('pagination')]
    public function test_returns_empty_array_on_page_overflow(): void
    {
        $count = 5;
        $maps = Map::factory()->count($count)->create();
        MapListMeta::factory()
            ->count($count)
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'created_on' => now()->subHour(),
            ])
            ->create();

        $actual = $this->getJson('/api/maps?page=999')
            ->assertStatus(200)
            ->json();

        $expected = [
            'data' => [],
            'meta' => [
                'current_page' => 999,
                'last_page' => 1,
                'per_page' => 100,
                'total' => $count,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('pagination')]
    public function test_caps_per_page_at_maximum(): void
    {
        $this->getJson('/api/maps?per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('per_page');
    }
}
