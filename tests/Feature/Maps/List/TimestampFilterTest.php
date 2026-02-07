<?php

namespace Tests\Feature\Maps\List;

use App\Models\Map;
use App\Models\MapListMeta;
use Tests\Helpers\MapTestHelper;
use Tests\TestCase;

class TimestampFilterTest extends TestCase
{
    #[Group('get')]
    #[Group('maps')]
    #[Group('timestamp')]
    public function test_default_timestamp_returns_active_maps(): void
    {
        $now = now();

        $includedMaps = Map::factory()->count(3)->create();
        $includedMetas = MapListMeta::factory()
            ->count(3)
            ->sequence(fn($seq) => [
                'code' => $includedMaps[$seq->index]->code,
                'created_on' => $now->copy()->subHour()->addSeconds($seq->index),
                'deleted_on' => null,
            ])
            ->create();

        // Obsolete versions
        MapListMeta::factory()
            ->for($includedMaps[0])
            ->sequence(fn($seq) => [
                'code' => $includedMaps[$seq->index]->code,
                'created_on' => $now->copy()->subHours(2)->addSeconds($seq->index),
                'deleted_on' => null,
            ])
            ->create();

        // Future versions
        Map::factory()->count(2)->withMeta([
            'created_on' => $now->addHour(),
        ])->create();

        // Active but deleted previously
        Map::factory()->count(2)->withMeta([
            'created_on' => $now->subHour(),
            'deleted_on' => $now->subMinute(),
        ])->create();

        $actual = $this->getJson('/api/maps')
            ->assertStatus(200)
            ->json();

        $expected = [
            'data' => $includedMaps->zip($includedMetas)
                ->map(fn($pair) => MapTestHelper::mergeMapMeta($pair[0], $pair[1]))
                ->values()
                ->toArray(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => 3,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('timestamp')]
    public function test_historical_timestamp_returns_maps_active_at_that_time(): void
    {
        $timestamp = now()->subHours(2);

        $includedMaps = Map::factory()->count(3)->create();
        $includedMetas = MapListMeta::factory()
            ->count(3)
            ->sequence(fn($seq) => [
                'code' => $includedMaps[$seq->index]->code,
                'created_on' => $timestamp->copy()->subHour()->addSeconds($seq->index),
                'deleted_on' => null,
            ])
            ->create();

        Map::factory()->count(2)->withMeta([
            'created_on' => $timestamp->copy()->addHour(),
        ])->create();

        Map::factory()->count(2)->withMeta([
            'created_on' => $timestamp->copy()->subHours(2),
            'deleted_on' => $timestamp->copy()->subHour(),
        ])->create();

        $actual = $this->getJson('/api/maps?timestamp=' . $timestamp->timestamp)
            ->assertStatus(200)
            ->json();

        $expected = [
            'data' => $includedMaps->zip($includedMetas)
                ->map(fn($pair) => MapTestHelper::mergeMapMeta($pair[0], $pair[1]))
                ->values()
                ->toArray(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => 3,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('timestamp')]
    public function test_future_timestamp_returns_non_deleted_maps(): void
    {
        $future = now()->addHours(2);

        $includedMaps = Map::factory()->count(5)->create();
        $includedMetas = MapListMeta::factory()
            ->count($includedMaps->count())
            ->sequence(fn($seq) => [
                'code' => $includedMaps[$seq->index]->code,
                'created_on' => now()->subHour()->addSeconds($seq->index),
                'deleted_on' => $seq->index < 3 ? null : $future->copy()->addHour(),
            ])
            ->create();

        Map::factory()->withMeta([
            'created_on' => now()->subHour(),
            'deleted_on' => $future->copy()->subHour(),
        ])->create();

        $actual = $this->getJson('/api/maps?timestamp=' . $future->timestamp)
            ->assertStatus(200)
            ->json();

        $expected = [
            'data' => $includedMaps->zip($includedMetas)
                ->map(fn($pair) => MapTestHelper::mergeMapMeta($pair[0], $pair[1]))
                ->values()
                ->toArray(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => $includedMaps->count(),
            ],
        ];

        $this->assertEquals($expected, $actual);
    }
}
