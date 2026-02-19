<?php

namespace Tests\Feature;

use App\Constants\FormatConstants;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\RetroMap;
use Tests\TestCase;

class DeleteMapTest extends TestCase
{
    #[Group('delete')]
    #[Group('maps')]
    public function test_delete_map_with_no_edit_map_permission_returns_403(): void
    {
        $user = $this->createUserWithPermissions([]);
        $map = Map::factory()->withMeta(['placement_curver' => 5])->create();

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/{$map->code}")
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to delete maps']);
    }

    /**
     * @dataProvider deleteMapClearsFieldProvider
     */
    #[Group('delete')]
    #[Group('maps')]
    public function test_delete_map_clears_expected_field(int|null $formatId, string $field, $expectedValue): void
    {
        $user = $this->createUserWithPermissions([$formatId => ['edit:map']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create([
            'placement_curver' => 10,
            'placement_allver' => 20,
            'difficulty' => 3,
            'botb_difficulty' => 2,
            'remake_of' => RetroMap::factory()->create()->id,
        ]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/{$map->code}")
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        $expected = [$field => $expectedValue, 'deleted_on' => null];
        $this->assertEquals($expected, array_intersect_key($actual, $expected));
    }

    public static function deleteMapClearsFieldProvider(): array
    {
        return [
            'maplist clears placement_curver' => [FormatConstants::MAPLIST, 'placement_curver', null],
            'maplist all versinos clears placement_allver' => [FormatConstants::MAPLIST_ALL_VERSIONS, 'placement_allver', null],
            'expert list clears difficulty' => [FormatConstants::EXPERT_LIST, 'difficulty', null],
            'best of the best clears botb_difficulty' => [FormatConstants::BEST_OF_THE_BEST, 'botb_difficulty', null],
            'nostalgia pack clears remake_of' => [FormatConstants::NOSTALGIA_PACK, 'remake_of', null],
        ];
    }

    #[Group('delete')]
    #[Group('maps')]
    public function test_delete_map_without_maplist_permission_keeps_placement_curver(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::EXPERT_LIST => ['edit:map']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->empty()->create([
            'placement_curver' => 10,
            'placement_allver' => 20,
            'difficulty' => 3,
        ]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/{$map->code}")
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        $expected = [
            'placement_curver' => 10,
            'placement_allver' => 20,
            'difficulty' => null,
            'botb_difficulty' => null,
            'remake_of' => null,
            'deleted_on' => null,
        ];
        $this->assertEquals($expected, array_intersect_key($actual, $expected));
    }

    #[Group('delete')]
    #[Group('maps')]
    public function test_delete_map_with_global_permission_clears_all_fields(): void
    {
        $retroMap = RetroMap::factory()->create();
        $user = $this->createUserWithPermissions([null => ['edit:map']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create([
            'placement_curver' => 10,
            'placement_allver' => 20,
            'difficulty' => 3,
            'botb_difficulty' => 2,
            'remake_of' => $retroMap->id,
        ]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/{$map->code}")
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        $expected = [
            'placement_curver' => null,
            'placement_allver' => null,
            'difficulty' => null,
            'botb_difficulty' => null,
            'remake_of' => null,
        ];
        $this->assertEquals($expected, array_intersect_key($actual, $expected));
        $this->assertNotNull($actual['deleted_on']);
    }

    #[Group('delete')]
    #[Group('maps')]
    public function test_delete_map_with_curver_permission_reranks_other_maps(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $now = now();
        $maps = Map::factory()->count(5)->create();
        MapListMeta::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'placement_curver' => $seq->index + 1,
                'placement_allver' => $seq->index + 1,
                'created_on' => $now->copy()->subYear(),
            ])
            ->create();

        $deleteIdx = 2;
        $mapToDelete = $maps[$deleteIdx];

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/{$mapToDelete->code}")
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');

        foreach ($maps as $idx => $map) {
            if ($map->code === $mapToDelete->code) {
                continue;
            }

            $actualMap = collect($actual)->first(fn($m) => $m['code'] === $map->code);
            $expectedPlacement = $idx < $deleteIdx ? $idx + 1 : $idx;

            $this->assertEquals(
                $expectedPlacement,
                $actualMap['placement_curver'],
                "Map at original position " . ($idx + 1) . " has wrong placement_curver"
            );
        }

        $deletedMap = $this->actingAs($user, 'discord')
            ->getJson("/api/maps/{$mapToDelete->code}")
            ->assertStatus(200)
            ->json();

        $this->assertNull($deletedMap['placement_curver']);
        $this->assertEquals($deleteIdx + 1, $deletedMap['placement_allver']);
    }

    #[Group('delete')]
    #[Group('maps')]
    public function test_delete_map_with_allver_permission_reranks_other_maps(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST_ALL_VERSIONS => ['edit:map']]);

        $now = now();
        $maps = Map::factory()->count(5)->create();
        MapListMeta::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'placement_curver' => $seq->index + 1,
                'placement_allver' => $seq->index + 1,
                'created_on' => $now->copy()->subYear(),
            ])
            ->create();

        $deleteIdx = 2;
        $mapToDelete = $maps[$deleteIdx];

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/{$mapToDelete->code}")
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST_ALL_VERSIONS)
            ->assertStatus(200)
            ->json('data');

        foreach ($maps as $idx => $map) {
            if ($map->code === $mapToDelete->code) {
                continue;
            }

            $actualMap = collect($actual)->first(fn($m) => $m['code'] === $map->code);
            $expectedPlacement = $idx < $deleteIdx ? $idx + 1 : $idx;

            $this->assertEquals(
                $expectedPlacement,
                $actualMap['placement_allver'],
                "Map at original position " . ($idx + 1) . " has wrong placement_allver"
            );
        }

        $deletedMap = $this->actingAs($user, 'discord')
            ->getJson("/api/maps/{$mapToDelete->code}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals($deleteIdx + 1, $deletedMap['placement_curver']);
        $this->assertNull($deletedMap['placement_allver']);
    }

    #[Group('delete')]
    #[Group('maps')]
    public function test_delete_map_partially_does_not_set_deleted_on(): void
    {
        $retroMap = RetroMap::factory()->create();
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create([
            'placement_curver' => 10,
            'placement_allver' => 20,
            'difficulty' => 3,
            'botb_difficulty' => 2,
            'remake_of' => $retroMap->id,
        ]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/{$map->code}")
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        $expected = [
            'deleted_on' => null,
            'placement_curver' => null,
            'placement_allver' => 20,
        ];
        $this->assertEquals($expected, array_intersect_key($actual, $expected));
    }

    #[Group('delete')]
    #[Group('maps')]
    public function test_delete_map_fully_sets_deleted_on(): void
    {
        $user = $this->createUserWithPermissions([
            FormatConstants::MAPLIST => ['edit:map'],
            FormatConstants::MAPLIST_ALL_VERSIONS => ['edit:map'],
            FormatConstants::EXPERT_LIST => ['edit:map'],
            FormatConstants::BEST_OF_THE_BEST => ['edit:map'],
            FormatConstants::NOSTALGIA_PACK => ['edit:map'],
        ]);
        $map = Map::factory()->create();
        MapListMeta::factory()->for($map)->create([
            'placement_curver' => 10,
            'placement_allver' => 20,
        ]);

        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/{$map->code}")
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        // All list fields are null, so deleted_on should be set
        $this->assertNotNull($actual['deleted_on']);
    }

    #[Group('delete')]
    #[Group('maps')]
    public function test_delete_map_twice_is_idempotent(): void
    {
        $user = $this->createUserWithPermissions([null => ['edit:map']]);
        $map = Map::factory()->create();
        $meta = MapListMeta::factory()->for($map)->create(['placement_curver' => 10]);

        $initialCount = MapListMeta::where('code', $map->code)->count();

        // First delete
        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/{$map->code}")
            ->assertStatus(204);

        $this->assertEquals($initialCount + 1, MapListMeta::where('code', $map->code)->count());

        // Second delete should be idempotent - creates same state, so only one new meta
        $this->actingAs($user, 'discord')
            ->deleteJson("/api/maps/{$map->code}")
            ->assertStatus(204);

        $this->assertEquals($initialCount + 1, MapListMeta::where('code', $map->code)->count());

        // Verify the state via GET
        $actual = $this->actingAs($user, 'discord')
            ->getJson("/api/maps/{$map->code}")
            ->assertStatus(200)
            ->json();

        $this->assertNull($actual['placement_curver']);
    }
}
