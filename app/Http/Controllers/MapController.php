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


        // Get latest metadata for timestamp CTE
        $latsetMetaCte = MapListMeta::selectRaw('DISTINCT ON (code) *')
            ->where('created_on', '<=', $timestamp)
            ->orderBy('code')
            ->orderBy('created_on', 'desc');

        // Build query for MapListMeta to get active map codes
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
