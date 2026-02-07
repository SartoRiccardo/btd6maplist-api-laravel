<?php

namespace Tests\Feature\Maps\List;

use App\Models\Creator;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use Tests\Helpers\MapTestHelper;
use Tests\TestCase;

class CreatedByFilterTest extends TestCase
{
    #[Group('get')]
    #[Group('maps')]
    #[Group('created_by')]
    public function test_created_by_filters_maps_by_creator_discord_id(): void
    {
        $users = User::factory()
            ->count(2)
            ->create();

        $includedCount = 2;
        $maps = Map::factory()->count($includedCount + 3)->create();

        Creator::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => [
                'map_code' => $maps[$seq->index]->code,
                'user_id' => $seq->index < $includedCount ? $users[0]->discord_id : $users[1]->discord_id,
            ])
            ->create();

        $metas = MapListMeta::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'created_on' => now()->subSeconds(100 - $seq->index),
            ])
            ->create();

        $actual = $this->getJson("/api/maps?created_by={$users[0]->discord_id}")
            ->assertStatus(200)
            ->json();

        $expected = [
            'data' => $maps->take($includedCount)
                ->zip($metas->take($includedCount))
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
    #[Group('created_by')]
    public function test_created_by_with_non_existent_user_returns_empty_array(): void
    {
        $count = 5;
        $maps = Map::factory()->count($count)->create();
        MapListMeta::factory()
            ->count($count)
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
            ])
            ->create();

        $actual = $this->getJson('/api/maps?created_by=999999')
            ->assertStatus(200)
            ->json();

        $expected = [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => 0,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }
}
