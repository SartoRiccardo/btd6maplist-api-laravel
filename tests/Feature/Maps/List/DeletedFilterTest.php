<?php

namespace Tests\Feature\Maps\List;

use App\Models\Map;
use App\Models\MapListMeta;
use Tests\Helpers\MapTestHelper;
use Tests\TestCase;

class DeletedFilterTest extends TestCase
{
    #[Group('get')]
    #[Group('maps')]
    #[Group('deleted')]
    public function test_deleted_exclude_returns_only_non_deleted_maps(): void
    {
        $includedMaps = Map::factory()->count(3)->sequence(fn($s) => ['code' => "ml{$s->index}"])->create();
        $includedMetas = MapListMeta::factory()
            ->count(3)
            ->sequence(fn($seq) => [
                'code' => $includedMaps[$seq->index]->code,
                'deleted_on' => null,
                'created_on' => now()->subSeconds(10 - $seq->index),
            ])
            ->create();

        Map::factory()->count(2)->withMeta([
            'deleted_on' => now()->subHour(),
        ])->create();

        $actual = $this->getJson('/api/maps?deleted=exclude')
            ->assertStatus(200)
            ->json();

        $expected = MapTestHelper::expectedMapLists($includedMaps, $includedMetas);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('deleted')]
    public function test_deleted_only_returns_only_deleted_maps(): void
    {
        $includedMaps = Map::factory()->count(2)->create();
        $includedMetas = MapListMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'code' => $includedMaps[$seq->index]->code,
                'deleted_on' => now()->subHour(),
            ])
            ->create();

        Map::factory()->count(3)->withMeta(['deleted_on' => null])->create();

        $actual = $this->getJson('/api/maps?deleted=only')
            ->assertStatus(200)
            ->json();

        $actualCodes = collect($actual['data'])->pluck('code');
        $metasByKey = $includedMetas->keyBy('code');
        $mapsByKey = $includedMaps->keyBy('code');

        $expected = [
            'data' => $actualCodes
                ->map(fn($code) => MapTestHelper::mergeMapMeta($mapsByKey->get($code), $metasByKey->get($code)))
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
    #[Group('deleted')]
    public function test_deleted_any_returns_all_maps(): void
    {
        $maps1 = Map::factory()->count(2)->create();
        $metas1 = MapListMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'code' => $maps1[$seq->index]->code,
                'deleted_on' => null,
            ])
            ->create();

        $maps2 = Map::factory()->count(3)->create();
        $metas2 = MapListMeta::factory()
            ->count(3)
            ->sequence(fn($seq) => [
                'code' => $maps2[$seq->index]->code,
                'deleted_on' => now()->subHour(),
            ])
            ->create();

        $allMetas = $metas1->concat($metas2);
        $metasByKey = $allMetas->keyBy('code');

        $actual = $this->getJson('/api/maps?deleted=any')
            ->assertStatus(200)
            ->json();

        $actualCodes = collect($actual['data'])->pluck('code');
        $mapsByKey = $maps1->concat($maps2)->keyBy('code');

        $expected = [
            'data' => $actualCodes
                ->map(fn($code) => MapTestHelper::mergeMapMeta($mapsByKey->get($code), $metasByKey->get($code)))
                ->values()
                ->toArray(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => 5,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }
}
