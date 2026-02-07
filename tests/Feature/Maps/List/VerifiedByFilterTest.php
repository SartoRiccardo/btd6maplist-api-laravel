<?php

namespace Tests\Feature\Maps\List;

use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\User;
use App\Models\Verification;
use Tests\Helpers\MapTestHelper;
use Tests\TestCase;

class VerifiedByFilterTest extends TestCase
{
    #[Group('get')]
    #[Group('maps')]
    #[Group('verified_by')]
    public function test_verified_by_filters_maps_by_verifier_discord_id(): void
    {
        $users = User::factory()
            ->count(2)
            ->create();

        $includedCount = 2;
        $maps = Map::factory()->count($includedCount + 3)->create();

        Verification::factory()
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
                'created_on' => now()->subHours($maps->count() - $seq->index),
            ])
            ->create();

        $actual = $this->getJson("/api/maps?verified_by={$users[0]->discord_id}")
            ->assertStatus(200)
            ->json();

        $expected = MapTestHelper::expectedMapLists($maps->take($includedCount), $metas->take($includedCount));

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('verified_by')]
    public function test_verified_by_with_non_existent_user_returns_empty_array(): void
    {
        $count = 5;
        $maps = Map::factory()->count($count)->create();
        MapListMeta::factory()
            ->count($count)
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
            ])
            ->create();

        $actual = $this->getJson('/api/maps?verified_by=999999')
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
