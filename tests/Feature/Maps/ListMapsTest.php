<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\Map;
use App\Models\MapListMeta;
use Tests\Helpers\MapTestHelper;
use Tests\TestCase;

class ListMapsTest extends TestCase
{
    // --- 1. BASIC PAGINATION --- //

    #[Group('get')]
    #[Group('maps')]
    #[Group('pagination')]
    public function test_returns_maps_with_default_pagination(): void
    {
        // Should return page 1 with per_page=100 by default
        // Assert: response has 200 status, meta.pagination is correct
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('pagination')]
    public function test_returns_maps_with_custom_pagination(): void
    {
        // Request page=2, per_page=10
        // Assert: response returns second page with 10 items
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('pagination')]
    public function test_returns_empty_array_on_page_overflow(): void
    {
        // Request page beyond available data
        // Assert: response has 200 status, data is empty array [], meta shows correct pagination info
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('pagination')]
    public function test_caps_per_page_at_maximum(): void
    {
        // Request per_page=500 (exceeds max of 100)
        // Assert: response returns max 100 items, not 500
    }

    // --- 2. TIMESTAMP FILTER --- //

    #[Group('get')]
    #[Group('maps')]
    #[Group('timestamp')]
    public function test_default_timestamp_returns_active_maps(): void
    {
        // Seed: Maps with created_on <= now AND (deleted_on IS NULL OR deleted_on > now) - SHOULD BE RETURNED
        // Seed: Maps with created_on > now (future) - SHOULD NOT BE RETURNED
        // Seed: Maps with deleted_on <= now (already deleted) - SHOULD NOT BE RETURNED
        // Assert: Only active maps are in response data
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('timestamp')]
    public function test_historical_timestamp_returns_maps_active_at_that_time(): void
    {
        // Seed: Maps created before timestamp and not deleted yet at timestamp - SHOULD BE RETURNED
        // Seed: Maps created after timestamp - SHOULD NOT BE RETURNED
        // Seed: Maps already deleted at timestamp - SHOULD NOT BE RETURNED
        // Assert: Only maps active at historical timestamp are returned
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('timestamp')]
    public function test_future_timestamp_returns_non_deleted_maps(): void
    {
        // Seed: Maps with deleted_on IS NULL - SHOULD BE RETURNED
        // Seed: Maps with deleted_on > future timestamp - SHOULD BE RETURNED
        // Seed: Maps with deleted_on <= future timestamp - SHOULD NOT BE RETURNED
        // Assert: Maps that would still be active at future time are returned
    }

    // --- 3. FORMAT FILTER --- //

    #[Group('get')]
    #[Group('maps')]
    #[Group('format')]
    public function test_maplist_format_filters_by_placement_curver(): void
    {
        $mapCount = Config::loadVars(['map_count'])->get('map_count', 50);

        $maps = Map::factory()->count($mapCount + 10)->create();
        $metas = MapListMeta::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index]->code,
                'placement_curver' => $seq->index + 1,
            ])
            ->create();

        // Create additional maps with null placement_curver (should be excluded)
        Map::factory()->count(10)->withMeta(['placement_curver' => null])->create();

        $actual = $this->getJson('/api/maps?format_id=1')
            ->assertStatus(200)
            ->json();

        $expected = [
            'data' => $maps->zip($metas)
                ->take($mapCount)
                ->map(fn($pair) => MapTestHelper::mergeMapMeta($pair[0], $pair[1]))
                ->values()
                ->toArray(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => $mapCount,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format')]
    public function test_maplist_all_versions_format_filters_by_placement_allver(): void
    {
        // Seed: Maps with placement_allver between 1-50 - SHOULD BE RETURNED
        // Seed: Maps with placement_allver = null - SHOULD NOT BE RETURNED
        // Seed: Maps with placement_allver > 50 - SHOULD NOT BE RETURNED
        // Assert: Only maps with placement_allver in range are returned, ordered by placement_allver ASC
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format')]
    public function test_expert_list_format_filters_by_difficulty_not_null(): void
    {
        // Seed: Maps with difficulty not null - SHOULD BE RETURNED
        // Seed: Maps with difficulty null - SHOULD NOT BE RETURNED
        // Assert: Only maps with difficulty are returned, ordered by difficulty ASC
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format')]
    public function test_best_of_the_best_format_filters_by_botb_difficulty_not_null(): void
    {
        // Seed: Maps with botb_difficulty not null - SHOULD BE RETURNED
        // Seed: Maps with botb_difficulty null - SHOULD NOT BE RETURNED
        // Assert: Only maps with botb_difficulty are returned, ordered by botb_difficulty ASC
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format')]
    public function test_nostalgia_pack_format_filters_by_remake_of_and_includes_retro_map(): void
    {
        // Seed: Maps with remake_of not null - SHOULD BE RETURNED (with retro_map relationship)
        // Seed: Maps with remake_of null - SHOULD NOT BE RETURNED
        // Assert: Only remake maps are returned, each includes retro_map with nested game
    }

    // --- 4. FORMAT SUBFILTER --- //

    #[Group('get')]
    #[Group('maps')]
    #[Group('format_subfilter')]
    public function test_expert_list_with_subfilter_filters_by_specific_difficulty(): void
    {
        // Seed: Maps with difficulty = 2 - SHOULD BE RETURNED
        // Seed: Maps with difficulty = 3 - SHOULD NOT BE RETURNED
        // Seed: Maps with difficulty null - SHOULD NOT BE RETURNED
        // Assert: Only maps with matching difficulty are returned
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format_subfilter')]
    public function test_best_of_the_best_with_subfilter_filters_by_specific_botb_difficulty(): void
    {
        // Seed: Maps with botb_difficulty = 4 - SHOULD BE RETURNED
        // Seed: Maps with botb_difficulty = 3 - SHOULD NOT BE RETURNED
        // Seed: Maps with botb_difficulty null - SHOULD NOT BE RETURNED
        // Assert: Only maps with matching botb_difficulty are returned
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format_subfilter')]
    public function test_nostalgia_pack_with_subfilter_filters_by_retro_game_game_id(): void
    {
        // Seed: Maps with retroGame.game_id = 123 - SHOULD BE RETURNED
        // Seed: Maps with retroGame.game_id = 456 - SHOULD NOT BE RETURNED
        // Assert: Only maps with matching retro game_id are returned
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format_subfilter')]
    public function test_subfilter_without_format_id_is_ignored(): void
    {
        // Seed: Various active maps
        // Request: format_subfilter only (no format_id)
        // Assert: All active maps are returned (subfilter ignored)
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('format_subfilter')]
    public function test_subfilter_with_maplist_format_is_ignored(): void
    {
        // Seed: Maps with placement_curver in range
        // Request: format_id=1, format_subfilter=2
        // Assert: MAPLIST results returned (subfilter ignored)
    }

    // --- 5. DELETED STATUS FILTER --- //

    #[Group('get')]
    #[Group('maps')]
    #[Group('deleted')]
    public function test_deleted_exclude_returns_only_non_deleted_maps(): void
    {
        // Seed: Maps with deleted_on IS NULL - SHOULD BE RETURNED
        // Seed: Maps with deleted_on IS NOT NULL - SHOULD NOT BE RETURNED
        // Request: deleted=exclude (or default, no deleted param)
        // Assert: Only non-deleted maps are returned
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('deleted')]
    public function test_deleted_only_returns_only_deleted_maps(): void
    {
        // Seed: Maps with deleted_on IS NOT NULL - SHOULD BE RETURNED
        // Seed: Maps with deleted_on IS NULL - SHOULD NOT BE RETURNED
        // Request: deleted=only
        // Assert: Only deleted maps are returned
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('deleted')]
    public function test_deleted_any_returns_all_maps(): void
    {
        // Seed: Maps with deleted_on IS NULL - SHOULD BE RETURNED
        // Seed: Maps with deleted_on IS NOT NULL - SHOULD BE RETURNED
        // Request: deleted=any
        // Assert: Both deleted and non-deleted maps are returned
    }

    // --- 6. CREATED BY FILTER --- //

    #[Group('get')]
    #[Group('maps')]
    #[Group('created_by')]
    public function test_created_by_filters_maps_by_creator_discord_id(): void
    {
        // Seed: Maps created by user_id=123 - SHOULD BE RETURNED
        // Seed: Maps created by user_id=456 - SHOULD NOT BE RETURNED
        // Request: created_by=123
        // Assert: Only maps created by specified user are returned
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('created_by')]
    public function test_created_by_with_non_existent_user_returns_empty_array(): void
    {
        // Seed: Various maps
        // Request: created_by=999999 (non-existent)
        // Assert: Empty array returned, meta shows total=0
    }

    // --- 7. VERIFIED BY FILTER --- //

    #[Group('get')]
    #[Group('maps')]
    #[Group('verified_by')]
    public function test_verified_by_filters_maps_by_verifier_discord_id(): void
    {
        // Seed: Maps verified by user_id=789 - SHOULD BE RETURNED
        // Seed: Maps verified by user_id=999 - SHOULD NOT BE RETURNED
        // Request: verified_by=789
        // Assert: Only maps verified by specified user are returned
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('verified_by')]
    public function test_verified_by_with_non_existent_user_returns_empty_array(): void
    {
        // Seed: Various maps
        // Request: verified_by=999999 (non-existent)
        // Assert: Empty array returned, meta shows total=0
    }

    // --- 8. VALIDATION ERRORS --- //

    #[Group('get')]
    #[Group('maps')]
    #[Group('validation')]
    public function test_invalid_format_id_returns_validation_error(): void
    {
        // Request: format_id=99999 (doesn't exist in formats table)
        // Assert: 422 status, validation error for format_id
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('validation')]
    public function test_invalid_deleted_status_returns_validation_error(): void
    {
        // Request: deleted=invalid
        // Assert: 422 status, validation error for deleted
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('validation')]
    public function test_negative_page_returns_validation_error(): void
    {
        // Request: page=-1
        // Assert: 422 status, validation error for page
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('validation')]
    public function test_zero_per_page_returns_validation_error(): void
    {
        // Request: per_page=0
        // Assert: 422 status, validation error for per_page
    }

    // --- 9. COMBINED FILTERS --- //

    #[Group('get')]
    #[Group('maps')]
    #[Group('combined')]
    public function test_format_id_and_subfilter_both_apply(): void
    {
        // Seed: Maps with difficulty=2 - SHOULD BE RETURNED
        // Seed: Maps with difficulty=3 - SHOULD NOT BE RETURNED
        // Seed: Maps with difficulty=null - SHOULD NOT BE RETURNED
        // Request: format_id=51, format_subfilter=2
        // Assert: Only maps matching both criteria are returned
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('combined')]
    public function test_format_id_and_deleted_both_apply(): void
    {
        // Seed: Active non-deleted EXPERT_LIST maps - SHOULD BE RETURNED
        // Seed: Deleted EXPERT_LIST maps - SHOULD NOT BE RETURNED
        // Seed: Non-expert maps - SHOULD NOT BE RETURNED
        // Request: format_id=51, deleted=exclude
        // Assert: Only maps matching both filters are returned
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('combined')]
    public function test_timestamp_and_deleted_both_apply(): void
    {
        // Seed: Maps active at timestamp AND not deleted - SHOULD BE RETURNED
        // Seed: Maps active at timestamp BUT deleted - SHOULD NOT BE RETURNED
        // Seed: Maps not active at timestamp - SHOULD NOT BE RETURNED
        // Request: timestamp=<historical>, deleted=exclude
        // Assert: Only maps matching both filters are returned
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('combined')]
    public function test_multiple_filters_combined_work_correctly(): void
    {
        // Complex scenario with multiple filters: format_id, format_subfilter, deleted, timestamp
        // Seed: Maps matching ALL criteria - SHOULD BE RETURNED
        // Seed: Maps matching SOME criteria - SHOULD NOT BE RETURNED
        // Seed: Maps matching NO criteria - SHOULD NOT BE RETURNED
        // Assert: Only maps matching all applied filters are returned
    }

    // --- 10. RESPONSE STRUCTURE --- //

    #[Group('get')]
    #[Group('maps')]
    #[Group('response')]
    public function test_response_uses_testable_structure(): void
    {
        // Seed: Test maps
        // Assert: Each item in data array matches Map::jsonStructure()
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('response')]
    public function test_map_preview_url_defaults_to_ninja_kiwi_when_null(): void
    {
        // Seed: Map with map_preview_url = null in database
        // Assert: Response includes default URL: https://data.ninjakiwi.com/btd6/maps/map/{code}/preview
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('response')]
    public function test_retro_map_included_for_nostalgia_pack_format(): void
    {
        // Seed: Maps with remake_of (NOSTALGIA_PACK)
        // Request: format_id=11
        // Assert: Each map includes retro_map with nested game data
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('response')]
    public function test_retro_map_not_included_for_non_nostalgia_formats(): void
    {
        // Seed: Maps with remake_of
        // Request: format_id=51 (EXPERT_LIST)
        // Assert: Maps include remake_of field but NOT retro_map relationship
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('response')]
    public function test_pagination_order_preserved(): void
    {
        // Seed: Maps with specific order (e.g., difficulty values)
        // Request: format_id=51 (orders by difficulty ASC)
        // Assert: Maps are returned in correct order
    }
}
