<?php

namespace Tests\Feature\Maps\List;

use App\Constants\FormatConstants;
use App\Models\Map;
use App\Models\MapListMeta;
use Tests\Helpers\MapTestHelper;
use Tests\TestCase;

class CombinedFiltersTest extends TestCase
{
    #[Group('get')]
    #[Group('maps')]
    #[Group('combined')]
    public function test_format_id_and_deleted_both_apply(): void
    {
        $includedMaps = Map::factory()->count(2)->create();
        $includedMetas = MapListMeta::factory()
            ->count($includedMaps->count())
            ->sequence(fn($seq) => [
                'code' => $includedMaps[$seq->index]->code,
                'difficulty' => 1,
                'created_on' => now()->subMinutes($includedMaps->count() - $seq->index),
                'deleted_on' => null,
            ])
            ->create();

        $excludedMaps = Map::factory()->count(2)->create();
        MapListMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'code' => $excludedMaps[$seq->index]->code,
                'difficulty' => 1,
                'deleted_on' => now()->subHour(),
            ])
            ->create();

        Map::factory()->count(3)->withMeta([
            'difficulty' => null,
            'deleted_on' => null,
        ])->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::EXPERT_LIST . '&deleted=exclude')
            ->assertStatus(200)
            ->json();

        $metas = $includedMetas->sortBy('difficulty')->values();

        $expected = MapTestHelper::expectedMapLists($includedMaps, $metas);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('combined')]
    public function test_timestamp_and_deleted_both_apply(): void
    {
        $timestamp = now()->subHours(2);

        $includedMaps = Map::factory()->count(2)->create();
        $includedMetas = MapListMeta::factory()
            ->count($includedMaps->count())
            ->sequence(fn($seq) => [
                'code' => $includedMaps[$seq->index]->code,
                'created_on' => $timestamp->copy()->subHours($includedMaps->count() - $seq->index),
                'deleted_on' => null,
            ])
            ->create();

        $excludedMaps1 = Map::factory()->count(2)->create();
        MapListMeta::factory()
            ->count(2)
            ->sequence(fn($seq) => [
                'code' => $excludedMaps1[$seq->index]->code,
                'created_on' => $timestamp->copy()->subHour(),
                'deleted_on' => $timestamp->copy()->subMinute(),
            ])
            ->create();

        Map::factory()->count(2)->withMeta([
            'created_on' => $timestamp->copy()->addHour(),
        ])->create();

        $actual = $this->getJson('/api/maps?timestamp=' . $timestamp->timestamp . '&deleted=exclude')
            ->assertStatus(200)
            ->json();

        $expected = MapTestHelper::expectedMapLists($includedMaps, $includedMetas);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('combined')]
    public function test_multiple_filters_combined_work_correctly(): void
    {
        $targetDifficulty = 3;
        $timestamp = now()->subHours(2);

        $includedMaps = Map::factory()->count(2)->create();
        $includedMetas = MapListMeta::factory()
            ->count($includedMaps->count())
            ->sequence(fn($seq) => [
                'code' => $includedMaps[$seq->index]->code,
                'created_on' => $timestamp->copy()->subHours($includedMaps->count() - $seq->index),
                'difficulty' => $targetDifficulty,
                'deleted_on' => null,
            ])
            ->create()
            ->sortBy('difficulty')
            ->sortBy('created_on')
            ->values();

        $partialMatch1 = Map::factory()->create();
        MapListMeta::factory()->create([
            'code' => $partialMatch1->code,
            'created_on' => $timestamp->copy()->subHour(),
            'difficulty' => 4,
            'deleted_on' => null,
        ]);

        Map::factory()->count(2)->withMeta([
            'created_on' => $timestamp->copy()->subHour(),
            'difficulty' => null,
            'deleted_on' => null,
        ])->create();

        $actual = $this->getJson('/api/maps?format_id=' . FormatConstants::EXPERT_LIST . '&format_subfilter=' . $targetDifficulty . '&deleted=exclude&timestamp=' . $timestamp->timestamp)
            ->assertStatus(200)
            ->json();

        $expected = MapTestHelper::expectedMapLists($includedMaps, $includedMetas);

        $this->assertEquals($expected, $actual);
    }
}
