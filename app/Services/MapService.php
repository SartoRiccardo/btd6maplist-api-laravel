<?php

namespace App\Services;

use App\Constants\FormatConstants;
use App\Models\MapListMeta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MapService
{
    /**
     * Rerank map placements after a position change.
     *
     * This implements the SQL from the Python codebase to efficiently update
     * placements for all affected maps in a single query.
     *
     * @param int|null $curPositionFrom Old current version position (or null if wasn't set)
     * @param int|null $curPositionTo New current version position (or null if being cleared)
     * @param int|null $allPositionFrom Old all-time version position (or null if wasn't set)
     * @param int|null $allPositionTo New all-time version position (or null if being cleared)
     * @param string $ignoreCode Map code to exclude from reranking (the map being edited)
     * @param Carbon $now Timestamp for the operation
     * @return void
     */
    public function rerankPlacements(
        ?int $curPositionFrom,
        ?int $curPositionTo,
        ?int $allPositionFrom,
        ?int $allPositionTo,
        string $ignoreCode,
        Carbon $now
    ): void {
        $bigNumber = 1_000_000;

        $curChanged = $curPositionFrom !== $curPositionTo;
        $allChanged = $allPositionFrom !== $allPositionTo;

        if (!$curChanged && !$allChanged) {
            return;
        }

        $whereClauses = [];
        $curverSelector = 'placement_curver';
        $allverSelector = 'placement_allver';
        $selectBindings = [];
        $whereBindings = [];

        if ($curChanged) {
            $from = $curPositionFrom ?? $bigNumber;
            $to = $curPositionTo ?? $bigNumber;

            $curverSelector = "CASE WHEN (placement_curver BETWEEN LEAST(?::int, ?::int) AND GREATEST(?::int, ?::int))
            THEN placement_curver + SIGN(?::int - ?::int)
            ELSE placement_curver END";
            $selectBindings = array_merge($selectBindings, [$from, $to, $from, $to, $from, $to]);

            $whereClauses[] = "placement_curver BETWEEN LEAST(?::int, ?::int) AND GREATEST(?::int, ?::int)";
            $whereBindings = array_merge($whereBindings, [$from, $to, $from, $to]);
        }

        if ($allChanged) {
            $from = $allPositionFrom ?? $bigNumber;
            $to = $allPositionTo ?? $bigNumber;

            $allverSelector = "CASE WHEN (placement_allver BETWEEN LEAST(?::int, ?::int) AND GREATEST(?::int, ?::int))
            THEN placement_allver + SIGN(?::int - ?::int)
            ELSE placement_allver END";
            $selectBindings = array_merge($selectBindings, [$from, $to, $from, $to, $from, $to]);

            $whereClauses[] = "placement_allver BETWEEN LEAST(?::int, ?::int) AND GREATEST(?::int, ?::int)";
            $whereBindings = array_merge($whereBindings, [$from, $to, $from, $to]);
        }

        $whereClause = implode(' OR ', $whereClauses);

        $bindings = array_merge(
            $selectBindings,
            [$now->toDateTimeString()],
            [$now->toDateTimeString()],
            $whereBindings,
            [$ignoreCode],
        );

        DB::statement(
            "INSERT INTO map_list_meta
            (placement_curver, placement_allver, code, difficulty, botb_difficulty, optimal_heros, created_on)
        SELECT
            {$curverSelector},
            {$allverSelector},
            code, difficulty, botb_difficulty, optimal_heros,
            ?::timestamp
        FROM latest_maps_meta(?::timestamp) mlm
        WHERE mlm.deleted_on IS NULL
            AND ({$whereClause})
            AND mlm.code != ?",
            $bindings
        );

        Log::info("Reranked maps", [
            'curPositionFrom' => $curPositionFrom,
            'curPositionTo' => $curPositionTo,
            'allPositionFrom' => $allPositionFrom,
            'allPositionTo' => $allPositionTo,
            'ignoreCode' => $ignoreCode,
        ]);
    }

    /**
     * Clear the remake_of reference from the previous map that had this remake_of.
     *
     * When setting a remake_of on a map, we need to ensure that only one map
     * has this remake_of set at any given time. This creates a new MapListMeta
     * for the previous map to clear its remake_of field.
     *
     * @param int $remakeOf The retro_map ID that is being claimed
     * @param string $ignoreCode Map code to exclude (the map being set)
     * @param Carbon $now Timestamp for the operation
     * @return void
     */
    public function clearPreviousRemakeOf(int $remakeOf, string $ignoreCode, Carbon $now): void
    {
        $previousMeta = MapListMeta::activeAtTimestamp($now)
            ->where('remake_of', $remakeOf)
            ->where('code', '!=', $ignoreCode)
            ->first();

        if ($previousMeta) {
            MapListMeta::create([
                'code' => $previousMeta->code,
                'placement_curver' => $previousMeta->placement_curver,
                'placement_allver' => $previousMeta->placement_allver,
                'difficulty' => $previousMeta->difficulty,
                'optimal_heros' => $previousMeta->optimal_heros,
                'botb_difficulty' => $previousMeta->botb_difficulty,
                'remake_of' => null,
                'created_on' => $now,
                'deleted_on' => null,
            ]);

            Log::info('Cleared previous remake_of', [
                'remake_of' => $remakeOf,
                'previous_code' => $previousMeta->code,
                'new_code' => $ignoreCode,
            ]);
        }
    }

    /**
     * Permission to field mapping for MapListMeta
     */
    public function getPermissionFieldMapping(): array
    {
        return [
            FormatConstants::MAPLIST => 'placement_curver',
            FormatConstants::MAPLIST_ALL_VERSIONS => 'placement_allver',
            FormatConstants::EXPERT_LIST => 'difficulty',
            FormatConstants::BEST_OF_THE_BEST => 'botb_difficulty',
            FormatConstants::NOSTALGIA_PACK => 'remake_of',
        ];
    }

    /**
     * Filter meta fields based on user's format permissions
     *
     * @param array $input Validated request input
     * @param array $userFormatIds Format IDs where user has edit:map permission
     * @param MapListMeta|null $existingMeta Existing meta for PUT (null for POST)
     * @return array Filtered meta fields
     */
    public function filterMetaFieldsByPermissions(
        array $input,
        array $userFormatIds,
        ?MapListMeta $existingMeta = null
    ): array {
        $permissionFields = $this->getPermissionFieldMapping();
        $filtered = [];

        foreach ($permissionFields as $formatId => $field) {
            if (in_array($formatId, $userFormatIds)) {
                // User has permission for this field, use the value from input
                if (array_key_exists($field, $input)) {
                    $filtered[$field] = $input[$field];
                }
            } else {
                // User lacks permission for this field
                if ($existingMeta) {
                    // PUT: Use existing value
                    $filtered[$field] = $existingMeta->$field;
                } else {
                    // POST: Set to null
                    $filtered[$field] = null;
                }
            }
        }

        return $filtered;
    }
}
