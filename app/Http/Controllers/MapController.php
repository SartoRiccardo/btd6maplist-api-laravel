<?php

namespace App\Http\Controllers;

use App\Http\Requests\Map\IndexMapRequest;
use App\Models\Config;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\Verification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapController
{
    /**
     * Get a paginated list of maps.
     *
     * @OA\Get(
     *     path="/maps",
     *     summary="Get list of maps",
     *     description="Retrieves a paginated list of maps with optional filters. Maps are queried based on their metadata (MapListMeta) active at the specified timestamp.",
     *     tags={"Maps"},
     *     @OA\Parameter(name="timestamp", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/timestamp")),
     *     @OA\Parameter(name="format_id", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/format_id")),
     *     @OA\Parameter(name="format_subfilter", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/format_subfilter")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/page")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/per_page")),
     *     @OA\Parameter(name="deleted", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/deleted")),
     *     @OA\Parameter(name="created_by", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/created_by")),
     *     @OA\Parameter(name="verified_by", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapRequest/properties/verified_by")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Map")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(IndexMapRequest $request)
    {
        $validated = $request->validated();

        // Convert unix timestamp to Carbon instance
        $timestamp = Carbon::createFromTimestamp($validated['timestamp']);
        $page = $validated['page'];
        $perPage = $validated['per_page'];
        $deleted = $validated['deleted'] ?? 'exclude';
        $createdBy = $validated['created_by'] ?? null;
        $verifiedBy = $validated['verified_by'] ?? null;
        $formatId = $validated['format_id'] ?? null;
        $formatSubfilter = $validated['format_subfilter'] ?? null;

        $latsetMetaCte = MapListMeta::activeAtTimestamp($timestamp);
        $metaQuery = MapListMeta::from(DB::raw("({$latsetMetaCte->toSql()}) as map_list_meta"))
            ->setBindings($latsetMetaCte->getBindings())
            ->with(['retroMap.game'])
            ->forFormat($formatId)
            ->forFormatSubfilter($formatId, $formatSubfilter)
            ->sortForFormat($formatId);

        // Apply deleted filter
        if ($deleted === 'only') {
            $metaQuery->whereNotNull('deleted_on');
        } elseif ($deleted === 'exclude') {
            $metaQuery->where(function ($query) use ($timestamp) {
                $query->whereNull('deleted_on')
                    ->orWhere('deleted_on', '>', $timestamp);
            });
        }

        // Apply created_by filter
        if ($createdBy) {
            $metaQuery->whereHas('map.creators', function ($q) use ($createdBy) {
                $q->where('user_id', $createdBy);
            });
        }

        // Apply verified_by filter
        if ($verifiedBy) {
            $metaQuery->whereHas('map.verifications', function ($q) use ($verifiedBy) {
                $q->where('user_id', $verifiedBy);
            });
        }

        // Get distinct map codes
        $metaCodes = $metaQuery->paginate($perPage, ['*'], 'page', $page);

        $maps = Map::whereIn('code', $metaCodes->pluck('code'))
            ->get();

        // Get all verified map codes (any version)
        $verifiedMapCodes = Verification::getVerifiedMapCodes(
            Config::loadVars(['current_btd6_ver'])->get('current_btd6_ver'),
            $metaCodes->pluck('code')
        )
            ->flip()
            ->map(fn() => true);

        // Merge meta and map data for each code in pagination order
        $metasByKey = $metaCodes->keyBy('code');
        $mapsByKey = $maps->keyBy('code');
        $data = $metaCodes->pluck('code')
            ->map(function ($code) use ($metasByKey, $mapsByKey, $verifiedMapCodes) {
                $meta = $metasByKey->get($code);
                $map = $mapsByKey->get($code);

                if (!$map || !$meta) {
                    return null;
                }

                return [
                    ...$map->toArray(),
                    ...$meta->toArray(),
                    'is_verified' => $verifiedMapCodes->get($code, false),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $metaCodes->currentPage(),
                'last_page' => $metaCodes->lastPage(),
                'per_page' => $metaCodes->perPage(),
                'total' => $metaCodes->total(),
            ],
        ]);
    }

    public function show($id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function submit(Request $request)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function save(Request $request)
    {
        /*

    Legacy Python code to update placements to scale them down. creates a new maplistmeta for every single
    affected map in one fell swoop. Copy this implementation, hit the raw SQL directly.

@postgres
async def update_list_placements(
        cur_positions: tuple[int | None, int | None] | None = None,
        all_positions: tuple[int | None, int | None] | None = None,
        ignore_code: str | None = None,
        conn=None
) -> None:
    if cur_positions is not None and cur_positions[0] == cur_positions[1]:
        cur_positions = None
    if all_positions is not None and all_positions[0] == all_positions[1]:
        all_positions = None
    if cur_positions is all_positions is None:
        return

    selectors = []
    args = []
    base_idx = 2

    curver_selector = "placement_curver"
    if cur_positions:
        selectors.append(f"placement_curver BETWEEN LEAST(${base_idx}::int, ${base_idx+1}::int) AND GREATEST(${base_idx}::int, ${base_idx+1}::int)")
        curver_selector = f"""
            CASE WHEN (placement_curver BETWEEN LEAST(${base_idx}::int, ${base_idx+1}::int) AND GREATEST(${base_idx}::int, ${base_idx+1}::int))
            THEN placement_curver + SIGN(${base_idx}::int - ${base_idx+1}::int)
            ELSE placement_curver END
        """
        args += [*normalize_positions(cur_positions)]
        base_idx += 2

    allver_selector = "placement_allver"
    if all_positions:
        selectors.append(f"placement_allver BETWEEN LEAST(${base_idx}::int, ${base_idx+1}::int) AND GREATEST(${base_idx}::int, ${base_idx+1}::int)")
        allver_selector = f"""
            CASE WHEN (placement_allver BETWEEN LEAST(${base_idx}::int, ${base_idx + 1}::int) AND GREATEST(${base_idx}::int, ${base_idx + 1}::int))
            THEN placement_allver + SIGN(${base_idx}::int - ${base_idx + 1}::int)
            ELSE placement_allver END
        """
        args += [*normalize_positions(all_positions)]
        base_idx += 2

    await conn.execute(
        f"""
        INSERT INTO map_list_meta
            (placement_curver, placement_allver, code, difficulty, botb_difficulty, optimal_heros)
        SELECT
            {curver_selector},
            {allver_selector},
            code, difficulty, botb_difficulty, optimal_heros
        FROM latest_maps_meta(NOW()::timestamp) mlm
        WHERE mlm.deleted_on IS NULL
            AND ({" OR ".join(selectors)})
            AND mlm.code != $1
        """,
        ignore_code,
        *args,
    )
         */
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function destroy($id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }
}
