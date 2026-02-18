<?php

namespace App\Services;

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
        // Normalize positions - if from and to are the same, no reranking needed
        if ($curPositionFrom === $curPositionTo) {
            $curPositionFrom = null;
            $curPositionTo = null;
        }
        if ($allPositionFrom === $allPositionTo) {
            $allPositionFrom = null;
            $allPositionTo = null;
        }

        // If both cur and all positions are null, nothing to do
        if (
            $curPositionFrom === null && $curPositionTo === null
            && $allPositionFrom === null && $allPositionTo === null
        ) {
            return;
        }

        $bindings = [];
        $selectors = [];
        $baseIdx = 1;
        $curverSelector = 'placement_curver';
        $allverSelector = 'placement_allver';

        // Build current version selector
        if ($curPositionFrom !== null && $curPositionTo !== null) {
            $bindings[] = $curPositionFrom;
            $bindings[] = $curPositionTo;
            $baseIdx += 2;

            $curverSelector = "
                CASE WHEN (placement_curver BETWEEN LEAST(\$" . ($baseIdx - 2) . "::int, \$" . ($baseIdx - 1) . "::int) AND GREATEST(\$" . ($baseIdx - 2) . "::int, \$" . ($baseIdx - 1) . "::int))
                THEN placement_curver + SIGN(\$" . ($baseIdx - 2) . "::int - \$" . ($baseIdx - 1) . "::int)
                ELSE placement_curver END
            ";

            $selectors[] = "placement_curver BETWEEN LEAST(\$" . ($baseIdx - 2) . "::int, \$" . ($baseIdx - 1) . "::int) AND GREATEST(\$" . ($baseIdx - 2) . "::int, \$" . ($baseIdx - 1) . "::int)";
        }

        // Build all-time version selector
        if ($allPositionFrom !== null && $allPositionTo !== null) {
            $bindings[] = $allPositionFrom;
            $bindings[] = $allPositionTo;
            $baseIdx += 2;

            $allverSelector = "
                CASE WHEN (placement_allver BETWEEN LEAST(\$" . ($baseIdx - 2) . "::int, \$" . ($baseIdx - 1) . "::int) AND GREATEST(\$" . ($baseIdx - 2) . "::int, \$" . ($baseIdx - 1) . "::int))
                THEN placement_allver + SIGN(\$" . ($baseIdx - 2) . "::int - \$" . ($baseIdx - 1) . "::int)
                ELSE placement_allver END
            ";

            $selectors[] = "placement_allver BETWEEN LEAST(\$" . ($baseIdx - 2) . "::int, \$" . ($baseIdx - 1) . "::int) AND GREATEST(\$" . ($baseIdx - 2) . "::int, \$" . ($baseIdx - 1) . "::int)";
        }

        $bindings[] = $now;
        $bindings[] = $ignoreCode;
        $baseIdx += 1;
        $timestampIdx = $baseIdx;
        $ignoreCodeIdx = $baseIdx + 1;

        // Build and execute the query
        $sql = "
            INSERT INTO map_list_meta
                (placement_curver, placement_allver, code, difficulty, botb_difficulty, optimal_heros, created_on)
            SELECT
                {$curverSelector},
                {$allverSelector},
                code, difficulty, botb_difficulty, optimal_heros, \${$timestampIdx}::timestamp
            FROM latest_maps_meta(\${$timestampIdx}::timestamp) mlm
            WHERE mlm.deleted_on IS NULL
                AND (" . implode(' OR ', $selectors) . ")
                AND mlm.code != \${$ignoreCodeIdx}
        ";

        DB::statement($sql, $bindings);

        Log::info('Reranked map placements', [
            'cur_from' => $curPositionFrom,
            'cur_to' => $curPositionTo,
            'all_from' => $allPositionFrom,
            'all_to' => $allPositionTo,
            'ignore_code' => $ignoreCode,
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
}
