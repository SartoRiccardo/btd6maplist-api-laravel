<?php

namespace App\Http\Controllers;

use App\Http\Requests\Completion\IndexCompletionRequest;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\MapListMeta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompletionController
{
    /**
     * Get a paginated list of completions.
     *
     * @OA\Get(
     *     path="/completions",
     *     summary="Get list of completions",
     *     description="Retrieves a paginated list of completions with optional filters. Completions are queried based on their metadata (CompletionMeta) active at the specified timestamp.",
     *     tags={"Completions"},
     *     @OA\Parameter(name="timestamp", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/timestamp")),
     *     @OA\Parameter(name="format_id", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/format_id")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/page")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/per_page")),
     *     @OA\Parameter(name="player_id", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/player_id")),
     *     @OA\Parameter(name="map_code", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/map_code")),
     *     @OA\Parameter(name="deleted", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/deleted")),
     *     @OA\Parameter(name="pending", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/pending")),
     *     @OA\Parameter(name="no_geraldo", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/no_geraldo")),
     *     @OA\Parameter(name="lcc", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/lcc")),
     *     @OA\Parameter(name="black_border", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/black_border")),
     *     @OA\Parameter(name="sort_by", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/sort_by")),
     *     @OA\Parameter(name="sort_order", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/sort_order")),
     *     @OA\Parameter(name="include", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexCompletionRequest/properties/include")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Completion")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(IndexCompletionRequest $request)
    {
        $validated = $request->validated();

        // Convert unix timestamp to Carbon instance for database queries
        $timestamp = Carbon::createFromTimestamp($validated['timestamp']);
        $formatId = $validated['format_id'] ?? null;
        $page = $validated['page'];
        $perPage = $validated['per_page'];
        $deleted = $validated['deleted'] ?? 'exclude';
        $pending = $validated['pending'] ?? 'exclude';
        $noGeraldo = $validated['no_geraldo'] ?? 'any';
        $lcc = $validated['lcc'] ?? 'any';
        $blackBorder = $validated['black_border'] ?? 'any';
        $playerId = $validated['player_id'] ?? null;
        $mapCode = $validated['map_code'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'created_on';
        $sortOrder = $validated['sort_order'] ?? 'asc';
        $include = $validated['include'] ?? [];

        // Build query for CompletionMeta to get active completion IDs
        $latestMetaCte = CompletionMeta::activeAtTimestamp($timestamp);
        $metaQuery = CompletionMeta::from(DB::raw("({$latestMetaCte->toSql()}) as completions_meta"))
            ->setBindings($latestMetaCte->getBindings())
            ->with(['completion.map', 'completion.proofs', 'lcc', 'players']);

        // Apply deleted filter
        if ($deleted === 'only') {
            $metaQuery->whereNotNull('deleted_on');
        } elseif ($deleted === 'exclude') {
            $metaQuery->where(function ($query) use ($timestamp) {
                $query->whereNull('deleted_on')
                    ->orWhere('deleted_on', '>', $timestamp);
            });
        }

        // Apply pending filter (accepted_by_id is null)
        if ($pending === 'only') {
            $metaQuery->whereNull('accepted_by_id');
        } elseif ($pending === 'exclude') {
            $metaQuery->whereNotNull('accepted_by_id');
        }

        // Apply no_geraldo filter
        if ($noGeraldo === 'only') {
            $metaQuery->where('no_geraldo', true);
        } elseif ($noGeraldo === 'exclude') {
            $metaQuery->where('no_geraldo', false);
        }

        // Apply lcc filter (lcc_id is not null = has LCC)
        if ($lcc === 'only') {
            $metaQuery->whereNotNull('lcc_id');
        } elseif ($lcc === 'exclude') {
            $metaQuery->whereNull('lcc_id');
        }

        // Apply black_border filter
        if ($blackBorder === 'only') {
            $metaQuery->where('black_border', true);
        } elseif ($blackBorder === 'exclude') {
            $metaQuery->where('black_border', false);
        }

        // Apply player_id filter
        if ($playerId) {
            $metaQuery->whereHas('players', function ($q) use ($playerId) {
                $q->where('discord_id', $playerId);
            });
        }

        // Apply map_code filter
        if ($mapCode) {
            $metaQuery->whereHas('completion.map', function ($q) use ($mapCode) {
                $q->where('code', $mapCode);
            });
        }

        // Apply format_id filter
        if ($formatId) {
            $metaQuery->where('format_id', $formatId);
        }

        $metaPaginated = $metaQuery->orderBy($sortBy, $sortOrder)
            ->paginate($perPage, ['*'], 'page', $page);

        // Load map metadata if requested
        $mapMetadataByKey = collect();
        if (in_array('map.metadata', $include)) {
            $latestMetaCte = MapListMeta::activeAtTimestamp($timestamp);
            $mapCodes = $metaPaginated->pluck('completion.map.code')->unique()->filter();

            $mapMetadataByKey = MapListMeta::from(DB::raw("({$latestMetaCte->toSql()}) as map_list_meta"))
                ->setBindings($latestMetaCte->getBindings())
                ->with(['retroMap.game'])
                ->whereIn('code', $mapCodes)
                ->get()
                ->keyBy('code');
        }

        // Load current LCC IDs for these completions
        $lccIds = $metaPaginated->pluck('lcc_id')->unique()->filter()->values();
        $currentLccIds = collect();
        if ($lccIds->isNotEmpty()) {
            $currentLccIds = DB::table('lccs_by_map')
                ->whereIn('id', $lccIds)
                ->pluck('id');
        }

        // Build data array from paginated metas
        $data = $metaPaginated->map(function ($meta) use ($mapMetadataByKey, $currentLccIds) {
            $completion = $meta->completion;
            if (!$completion) {
                return null;
            }

            $result = [
                ...$meta->toArray(),
                ...$completion->toArray(),
                'map' => [
                    ...($mapMetadataByKey->get($completion->map->code) ?? collect())->toArray(),
                    ...$completion->map->toArray(),
                ],
                'is_current_lcc' => $meta->lcc_id ? $currentLccIds->contains($meta->lcc_id) : false,
            ];

            return $result;
        })
            ->filter()
            ->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $metaPaginated->currentPage(),
                'last_page' => $metaPaginated->lastPage(),
                'per_page' => $metaPaginated->perPage(),
                'total' => $metaPaginated->total(),
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

    public function transfer(Request $request)
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
