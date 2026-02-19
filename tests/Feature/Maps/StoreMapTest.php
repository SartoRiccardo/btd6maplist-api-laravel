<?php

namespace Tests\Feature;

use App\Constants\FormatConstants;
use App\Models\Config;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\RetroMap;
use App\Models\User;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;

class StoreMapTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/maps';
    }

    protected function method(): string
    {
        return 'POST';
    }

    protected function requestData(): array
    {
        return [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
        ];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 201;
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_no_edit_map_permission_returns_403(): void
    {
        $user = $this->createUserWithPermissions([]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden - You do not have permission to create maps']);
    }

    /**
     * @dataProvider storeMapFiltersByPermissionProvider
     */
    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_filters_by_permission(int|null $formatId, string $field): void
    {
        $retroMap = RetroMap::factory()->create();
        $user = $this->createUserWithPermissions([$formatId => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 1,
            'placement_allver' => 1,
            'difficulty' => 3,
            'optimal_heros' => ['Quincy'],
            'botb_difficulty' => 2,
            'remake_of' => $retroMap->id,
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        // The field they have permission for should be set
        $this->assertEquals($payload[$field], $actual[$field]);

        // All other meta fields should be null
        $metaFields = ['placement_curver', 'placement_allver', 'difficulty', 'botb_difficulty', 'remake_of'];
        foreach ($metaFields as $metaField) {
            if ($metaField !== $field) {
                $this->assertNull($actual[$metaField], "Field {$metaField} should be null for format " . ($formatId ?? 'null'));
            }
        }
    }

    public static function storeMapFiltersByPermissionProvider(): array
    {
        return [
            'maplist permission sets placement_curver' => [FormatConstants::MAPLIST, 'placement_curver'],
            'maplist all versions permission sets placement_allver' => [FormatConstants::MAPLIST_ALL_VERSIONS, 'placement_allver'],
            'expert list permission sets difficulty' => [FormatConstants::EXPERT_LIST, 'difficulty'],
            'best of the best permission sets botb_difficulty' => [FormatConstants::BEST_OF_THE_BEST, 'botb_difficulty'],
            'nostalgia pack sets remake_of' => [FormatConstants::NOSTALGIA_PACK, 'remake_of'],
        ];
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_global_permission_sets_all_meta_fields(): void
    {
        $retroMap = RetroMap::factory()->create();
        $user = $this->createUserWithPermissions([null => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'r6_start' => 10,
            'map_data' => '{}',
            'map_preview_url' => 'https://example.com/preview.png',
            'map_notes' => 'Test notes',
            'placement_curver' => 1,
            'placement_allver' => 1,
            'difficulty' => 3,
            'optimal_heros' => ['Quincy'],
            'botb_difficulty' => 2,
            'remake_of' => $retroMap->id,
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $expected = [
            'placement_curver' => 1,
            'placement_allver' => 1,
            'difficulty' => 3,
            'optimal_heros' => ['Quincy'],
            'botb_difficulty' => 2,
            'remake_of' => $retroMap->id,
        ];
        $this->assertEquals($expected, array_intersect_key($actual, $expected));
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_empty_payload_returns_all_required_field_errors(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $actual = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', [])
            ->assertStatus(422)
            ->json();

        $expected = ['code', 'name'];
        $actualKeys = array_keys($actual['errors']);
        sort($expected);
        sort($actualKeys);
        $this->assertEquals($expected, $actualKeys);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_code_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TOOLONGCODE123',
            'name' => 'Test Map',
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_duplicate_code_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $existingMap = Map::factory()->create();

        $payload = [
            'code' => $existingMap->code,
            'name' => 'Test Map',
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_name_returns_error(): void
    {
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => str_repeat('a', 256),
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_r6_start_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'r6_start' => 'not_an_integer',
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['r6_start']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_map_preview_url_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'map_preview_url' => 'not_a_url',
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['map_preview_url']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_map_notes_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'map_notes' => str_repeat('a', 1001),
        ];

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422);

        $actual = $response->json();
        $this->assertEquals(['map_notes'], array_keys($actual['errors']));
    }

    /*
prompt for tmrw

    test_store_map_with_invalid_r6_start_returns_error (wrong cuz its a url but ok) + test_store_map_with_invalid_map_preview_url_returns_error +
  test_store_map_with_invalid_map_notes_returns_error + test_store_map_with_invalid_difficulty_returns_error +
  test_store_map_with_invalid_botb_difficulty_returns_error + test_store_map_with_invalid_placement_allver_returns_error +
  test_store_map_with_invalid_placement_curver_returns_error + test_store_map_with_invalid_remake_of_returns_error +
  test_store_map_with_invalid_creators_returns_error + test_store_map_with_invalid_verifiers_returns_error +
  test_store_map_with_invalid_optimal_heros_returns_error can be ONE single test btw.
  */

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_placement_curver_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 0,
        ];

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422);

        $actual = $response->json();
        $this->assertEquals(['placement_curver'], array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_placement_allver_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST_ALL_VERSIONS => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_allver' => -1,
        ];

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422);

        $actual = $response->json();
        $this->assertEquals(['placement_allver'], array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_placement_curver_exceeding_max_returns_error_with_correct_max_value(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        // Create 5 maps with placement_curver set
        $maps = Map::factory()->count(5)->create();
        foreach ($maps as $idx => $map) {
            MapListMeta::factory()->for($map)->create(['placement_curver' => $idx + 1]);
        }

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 100,
        ];

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422);

        $errors = $response->json('errors.placement_curver');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('6', $errors[0]);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_placement_allver_exceeding_max_returns_error_with_correct_max_value(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST_ALL_VERSIONS => ['edit:map']]);

        // Create 5 maps with placement_allver set
        $maps = Map::factory()->count(5)->create();
        foreach ($maps as $idx => $map) {
            MapListMeta::factory()->for($map)->create(['placement_allver' => $idx + 1]);
        }

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_allver' => 100,
        ];

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422);

        $errors = $response->json('errors.placement_allver');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('6', $errors[0]);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_difficulty_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::EXPERT_LIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'difficulty' => 10,
        ];

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422);

        $actual = $response->json();
        $this->assertEquals(['difficulty'], array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_botb_difficulty_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::BEST_OF_THE_BEST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'botb_difficulty' => -1,
        ];

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422);

        $actual = $response->json();
        $this->assertEquals(['botb_difficulty'], array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_remake_of_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::NOSTALGIA_PACK => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'remake_of' => 99999,
        ];

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422);

        $actual = $response->json();
        $this->assertEquals(['remake_of'], array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_creators_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'creators' => [
                ['user_id' => 'invalid_user_id'],
            ],
        ];

        $response = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422);

        $actual = $response->json();
        $this->assertEquals(['creators.0.user_id'], array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_verifiers_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'verifiers' => [
                ['user_id' => 'invalid_user_id'],
            ],
        ];

        $actual = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->json();

        $this->assertEquals(['verifiers.0.user_id'], array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_numeric_creator_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'creators' => [
                ['user_id' => '123'], // Too short for Discord snowflake
            ],
        ];

        $actual = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->json();

        $this->assertEquals(['creators.0.user_id'], array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_numeric_verifier_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'verifiers' => [
                ['user_id' => '-12345678901234567'], // Negative number
            ],
        ];

        $actual = $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->json();

        $this->assertEquals(['verifiers.0.user_id'], array_keys($actual['errors']));
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_invalid_optimal_heros_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'optimal_heros' => 'not_an_array',
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['optimal_heros']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_creators_too_many_items_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $this->markTestIncomplete('optimal_heros limit validation not yet implemented');
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_creators_item_too_long_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $this->markTestIncomplete('optimal_heros item length validation not yet implemented');
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_happy_path_with_admin_sets_everything(): void
    {
        $this->markTestSkipped("needs checking");
        // Create existing maps with placements so we can set position 5 and 10
        $maps = Map::factory()->count(9)->create();
        foreach ($maps as $idx => $map) {
            MapListMeta::factory()->for($map)->create([
                'placement_curver' => $idx < 4 ? $idx + 1 : null, // Positions 1-4
                'placement_allver' => $idx + 1, // Positions 1-9
            ]);
        }

        $retroMap = RetroMap::factory()->create();
        $creator1 = User::factory()->create();
        $creator2 = User::factory()->create();
        $verifier1 = User::factory()->create();
        $verifier2 = User::factory()->create();

        $user = $this->createUserWithPermissions([null => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'r6_start' => 10,
            'map_data' => '{}',
            'map_preview_url' => 'https://example.com/preview.png',
            'map_notes' => 'Test notes',
            'placement_curver' => 5,
            'placement_allver' => 10,
            'difficulty' => 3,
            'optimal_heros' => ['Quincy', 'Gwendolin'],
            'botb_difficulty' => 2,
            'remake_of' => $retroMap->id,
            'creators' => [
                ['user_id' => $creator1->discord_id, 'role' => 'Gameplay'],
                ['user_id' => $creator2->discord_id, 'role' => 'Design'],
            ],
            'verifiers' => [
                ['user_id' => $verifier1->discord_id, 'version' => null],
                ['user_id' => $verifier2->discord_id, 'version' => null],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        // Remove nested objects for cleaner comparison
        foreach ($actual['creators'] as &$creator) {
            unset($creator['user']);
        }
        foreach ($actual['verifications'] as &$verification) {
            unset($verification['user']);
        }
        unset($actual['retro_map']);

        $expected = Map::jsonStructure([
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'r6_start' => '10',
            'map_data' => '{}',
            'map_preview_url' => 'https://example.com/preview.png',
            'map_notes' => 'Test notes',
            'placement_curver' => 5,
            'placement_allver' => 10,
            'difficulty' => 3,
            'optimal_heros' => ['Quincy', 'Gwendolin'],
            'botb_difficulty' => 2,
            'remake_of' => $retroMap->id,
            'deleted_on' => null,
            'is_verified' => true,
            'creators' => [
                ['user_id' => $creator1->discord_id, 'role' => 'Gameplay'],
                ['user_id' => $creator2->discord_id, 'role' => 'Design'],
            ],
            'verifications' => [
                ['user_id' => $verifier1->discord_id, 'version' => null],
                ['user_id' => $verifier2->discord_id, 'version' => null],
            ],
        ], exclude: ['retro_map']);

        $this->assertEquals($expected, $actual);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_at_position_n_shifts_other_maps_by_one(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        // Create 5 maps with placement_curver set
        $maps = Map::factory()->count(5)->create();
        foreach ($maps as $idx => $map) {
            MapListMeta::factory()->for($map)->create(['placement_curver' => $idx + 1]);
        }

        // Insert new map at position 3
        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'placement_curver' => 3,
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps?format_id=' . FormatConstants::MAPLIST)
            ->assertStatus(200)
            ->json('data');

        $placements = collect($actual)->pluck('placement_curver', 'code');

        // Original maps at positions 1-2 stay the same
        $this->assertEquals(1, $placements[$maps[0]->code]);
        $this->assertEquals(2, $placements[$maps[1]->code]);

        // New map is at position 3
        $this->assertEquals(3, $placements['TESTCODE']);

        // Original maps at positions 3-5 shifted to 4-6
        $this->assertEquals(4, $placements[$maps[2]->code]);
        $this->assertEquals(5, $placements[$maps[3]->code]);
        $this->assertEquals(6, $placements[$maps[4]->code]);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_remake_of_steals_from_existing_remake(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::NOSTALGIA_PACK => ['edit:map']]);

        $retroMap = RetroMap::factory()->create();
        $existingMap = Map::factory()->create();
        MapListMeta::factory()->for($existingMap)->create(['remake_of' => $retroMap->id]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'remake_of' => $retroMap->id,
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);

        // New map has the remake_of
        $newMap = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($retroMap->id, $newMap['remake_of']);

        // Old map no longer has the remake_of
        $oldMap = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/' . $existingMap->code)
            ->assertStatus(200)
            ->json();

        $this->assertNull($oldMap['remake_of']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_empty_creators_and_verifiers_works(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'creators' => [],
            'verifications' => [],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertIsArray($actual['creators']);
        $this->assertEmpty($actual['creators']);
        $this->assertIsArray($actual['verifications']);
        $this->assertEmpty($actual['verifications']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_without_creators_and_verifiers_works(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201)
            ->assertJson(['code' => 'TESTCODE']);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertIsArray($actual['creators']);
        $this->assertEmpty($actual['creators']);
        $this->assertIsArray($actual['verifications']);
        $this->assertEmpty($actual['verifications']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_creators_without_role_works(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $creator = User::factory()->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'creators' => [
                ['user_id' => $creator->discord_id],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $actual['creators']);
        $this->assertEquals($creator->discord_id, $actual['creators'][0]['user_id']);
        $this->assertNull($actual['creators'][0]['role']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_creators_with_role_works(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $creator = User::factory()->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'creators' => [
                ['user_id' => $creator->discord_id, 'role' => 'Gameplay'],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $actual['creators']);
        $this->assertEquals($creator->discord_id, $actual['creators'][0]['user_id']);
        $this->assertEquals('Gameplay', $actual['creators'][0]['role']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_verifiers_with_version_works(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $verifier = User::factory()->create();
        $currentVersion = Config::loadVars(['current_btd6_ver'])->get('current_btd6_ver');

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'verifiers' => [
                ['user_id' => $verifier->discord_id, 'version' => $currentVersion],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertArrayHasKey('verifications', $actual);
        $this->assertIsArray($actual['verifications']);
        $this->assertCount(1, $actual['verifications']);
        $this->assertEquals($verifier->discord_id, $actual['verifications'][0]['user_id']);
        $this->assertEquals($currentVersion, $actual['verifications'][0]['version']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_verifiers_without_version_works(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $verifier = User::factory()->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'verifiers' => [
                ['user_id' => $verifier->discord_id],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertArrayHasKey('verifications', $actual);
        $this->assertIsArray($actual['verifications']);
        $this->assertCount(1, $actual['verifications']);
        $this->assertEquals($verifier->discord_id, $actual['verifications'][0]['user_id']);
        $this->assertNull($actual['verifications'][0]['version']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_creators_with_various_roles_works(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $creator1 = User::factory()->create();
        $creator2 = User::factory()->create();
        $creator3 = User::factory()->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'creators' => [
                ['user_id' => $creator1->discord_id, 'role' => 'Gameplay'],
                ['user_id' => $creator2->discord_id, 'role' => null],
                ['user_id' => $creator3->discord_id, 'role' => 'Design'],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertCount(3, $actual['creators']);

        $creatorsByDiscordId = collect($actual['creators'])->keyBy('user_id');

        $this->assertEquals('Gameplay', $creatorsByDiscordId[$creator1->discord_id]['role']);
        $this->assertNull($creatorsByDiscordId[$creator2->discord_id]['role']);
        $this->assertEquals('Design', $creatorsByDiscordId[$creator3->discord_id]['role']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_verifiers_with_various_versions_works(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $verifier1 = User::factory()->create();
        $verifier2 = User::factory()->create();
        $verifier3 = User::factory()->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'verifiers' => [
                ['user_id' => $verifier1->discord_id, 'version' => null],
                ['user_id' => $verifier2->discord_id, 'version' => null],
                ['user_id' => $verifier3->discord_id, 'version' => null],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(201);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/maps/TESTCODE')
            ->assertStatus(200)
            ->json();

        $this->assertCount(3, $actual['verifications']);

        $verifiersByDiscordId = collect($actual['verifications'])->keyBy('user_id');

        $this->assertNull($verifiersByDiscordId[$verifier1->discord_id]['version']);
        $this->assertNull($verifiersByDiscordId[$verifier2->discord_id]['version']);
        $this->assertNull($verifiersByDiscordId[$verifier3->discord_id]['version']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_duplicate_creator_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $creator = User::factory()->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'creators' => [
                ['user_id' => $creator->discord_id],
                ['user_id' => $creator->discord_id],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['creators.1.user_id']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_duplicate_verifier_same_version_returns_error(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);
        $verifier = User::factory()->create();

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'verifiers' => [
                ['user_id' => $verifier->discord_id, 'version' => null],
                ['user_id' => $verifier->discord_id, 'version' => null],
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verifiers.1.user_id']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_map_preview_file_is_skipped(): void
    {
        $this->markTestSkipped("needs checking");
        $this->markTestIncomplete('Future feature - map preview file upload');
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_both_preview_url_and_file_file_takes_precedence_is_skipped(): void
    {
        $this->markTestSkipped("needs checking");
        $this->markTestIncomplete('Future feature - map preview file upload');
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_duplicate_alias_is_skipped(): void
    {
        $this->markTestSkipped("needs checking");
        $this->markTestIncomplete('Future feature - map aliases');
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_alias_taken_by_existing_map_errors_is_skipped(): void
    {
        $this->markTestSkipped("needs checking");
        $this->markTestIncomplete('Future feature - map aliases');
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_alias_taken_by_deleted_map_works_is_skipped(): void
    {
        $this->markTestSkipped("needs checking");
        $this->markTestIncomplete('Future feature - map aliases');
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_alias_case_insensitive_is_skipped(): void
    {
        $this->markTestSkipped("needs checking");
        $this->markTestIncomplete('Future feature - map aliases');
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_r6_start_future_feature_is_skipped(): void
    {
        $this->markTestSkipped("needs checking");
        $this->markTestIncomplete('Future feature - r6_start validation');
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_nonexistent_creator_returns_422(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'creators' => [
                ['user_id' => '123456789012345678'], // Non-existent user
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['creators.0.user_id']);
    }

    #[Group('store')]
    #[Group('maps')]
    public function test_store_map_with_nonexistent_verifier_returns_422(): void
    {
        $this->markTestSkipped("needs checking");
        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST => ['edit:map']]);

        $payload = [
            'code' => 'TESTCODE',
            'name' => 'Test Map',
            'verifiers' => [
                ['user_id' => '123456789012345678'], // Non-existent user
            ],
        ];

        $this->actingAs($user, 'discord')
            ->postJson('/api/maps', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verifiers.0.user_id']);
    }
}
