<?php

namespace App\Http\Controllers;

use App\Constants\FormatConstants;
use App\Http\Requests\MapCompletionsRequest;
use App\Http\Requests\MapIndexRequest;
use App\Http\Requests\MapSubmissionsRequest;
use App\Models\Completion;
use App\Models\Config;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\RetroMap;
use App\Models\Verification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Maps",
 *     description="Map endpoints"
 * )
 */
class MapController extends Controller
{
    /**
     * Get format-specific map list.
     *
     * @OA\Get(
     *     path="/maps",
     *     summary="Returns a list of maps in any maplist",
     *     tags={"Maps"},
     *     @OA\Parameter(name="format", in="query", schema=@OA\Schema(type="integer"), description="Format ID (1, 2, 11, 51, 52)"),
     *     @OA\Parameter(name="filter", in="query", schema=@OA\Schema(type="integer"), description="Filter value required for some formats"),
     *     @OA\Response(response=200, description="Array of MinimalMap", @OA\JsonContent(type="array", @OA\Items(
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="code", type="string"),
     *         @OA\Property(property="placement", type="integer", nullable=true),
     *         @OA\Property(property="is_verified", type="boolean"),
     *         @OA\Property(property="map_preview_url", type="string", nullable=true)
     *     ))),
     *     @OA\Response(response=400, description="Invalid request")
     * )
     */
    public function index(MapIndexRequest $request): JsonResponse
    {
        $formatId = (int) $request->input('format', 1);
        $filter = $request->input('filter');

        return match ($formatId) {
            FormatConstants::MAPLIST, FormatConstants::MAPLIST_ALL_VERSIONS => $this->getListMaps($formatId === FormatConstants::MAPLIST),
            FormatConstants::NOSTALGIA_PACK => $this->getNostalgiaPack($filter),
            FormatConstants::EXPERT_LIST => $this->getMapsByIdx('difficulty', $filter),
            FormatConstants::BEST_OF_THE_BEST => $this->getMapsByIdx('botb_difficulty', $filter),
            default => response()->json(['error' => 'Invalid format'], 400),
        };
    }

    /**
     * Get legacy/deleted maps.
     *
     * @OA\Get(
     *     path="/maps/legacy",
     *     summary="Returns a list of deleted/pushed off maps",
     *     tags={"Maps"},
     *     @OA\Response(response=200, description="Array of PartialListMap", @OA\JsonContent(type="array", @OA\Items(
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="code", type="string"),
     *         @OA\Property(property="placement", type="integer", nullable=true),
     *         @OA\Property(property="is_verified", type="boolean"),
     *         @OA\Property(property="map_preview_url", type="string", nullable=true)
     *     )))
     * )
     */
    public function legacy(): JsonResponse
    {
        // Get config values
        $config = Config::loadVars(['current_btd6_ver', 'map_count']);
        $currentBtd6Ver = $config->get('current_btd6_ver');
        $mapCount = $config->get('map_count');

        // Query metas with map relationship for legacy maps
        $metas = MapListMeta::with('map')
            ->whereNull('deleted_on')
            ->where(function ($query) use ($mapCount) {
                $query->where('placement_curver', '>', $mapCount)
                    ->orWhere(function ($q) {
                        $q->whereNull('placement_curver')
                            ->whereNull('difficulty')
                            ->whereNull('botb_difficulty')
                            ->whereNull('remake_of');
                    });
            })
            ->orderByRaw('placement_curver IS NULL')
            ->orderBy('placement_curver')
            ->get();

        // Get verified map codes (only for these maps)
        $mapCodes = $metas->pluck('code');
        $verifiedMapCodes = Verification::getVerifiedMapCodes($currentBtd6Ver, $mapCodes);

        // Format response
        return response()->json($metas->map(function ($meta) use ($verifiedMapCodes) {
            $map = $meta->map;
            return [
                'name' => $map->name,
                'code' => $map->code,
                'placement' => $meta->placement_curver,
                'is_verified' => $verifiedMapCodes->contains($map->code),
                'map_preview_url' => $map->map_preview_url,
            ];
        })->values());
    }

    /**
     * Get retro maps grouped by game/category.
     *
     * @OA\Get(
     *     path="/maps/retro",
     *     summary="Returns a list of retro maps grouped by game and category",
     *     tags={"Maps"},
     *     @OA\Response(
     *         response=200,
     *         description="Object with game names as keys, containing categories with maps",
     *         @OA\JsonContent(
     *             type="object",
     *             additionalProperties={
     *                 "type": "object",
     *                 "additionalProperties":{
     *                     "type":"array",
     *                     "items":{@OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string")}
     *                 }
     *             }
     *         )
     *     })
     * )
     */
    public function retro(): JsonResponse
    {
        $maps = DB::select("
            SELECT
                rm.name, rm.id, rm.sort_order, rm.preview_url,
                rm.game_id, rm.category_id, rm.subcategory_id,
                rg.game_name, rg.category_name, rg.subcategory_name
            FROM retro_maps rm
            JOIN retro_games rg
                ON rm.game_id = rg.game_id
                AND rm.category_id = rg.category_id
                AND rm.subcategory_id = rg.subcategory_id
            ORDER BY rm.sort_order
        ");

        $grouped = [];
        foreach ($maps as $map) {
            if (!isset($grouped[$map->game_name])) {
                $grouped[$map->game_name] = [];
            }
            if (!isset($grouped[$map->game_name][$map->category_name])) {
                $grouped[$map->game_name][$map->category_name] = [];
            }
            $grouped[$map->game_name][$map->category_name][] = [
                'id' => $map->id,
                'name' => $map->name,
            ];
        }

        return response()->json($grouped);
    }

    /**
     * Get map submissions.
     *
     * @OA\Get(
     *     path="/maps/submit",
     *     summary="Gets all map submissions",
     *     tags={"Submissions"},
     *     @OA\Parameter(name="page", in="query", schema=@OA\Schema(type="integer")),
     *     @OA\Parameter(name="pending", in="query", schema=@OA\Schema(type="string", enum={"pending", "all"})),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of submissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="pages", type="integer"),
     *             @OA\Property(property="submissions", type="array", @OA\Items(ref="#/components/schemas/MapSubmission"))
     *         )
     *     })
     * )
     */
    public function submissions(MapSubmissionsRequest $request): JsonResponse
    {
        $page = $request->getPage();
        $perPage = 50;
        $omitRejected = $request->getPending() === 'pending';

        // Use raw SQL for the complex LEFT JOIN with timestamp comparison
        $sql = "
            SELECT
                ms.id, ms.code, ms.submitter_id, ms.subm_notes, ms.format_id,
                ms.proposed, ms.rejected_by, ms.completion_proof,
                EXTRACT(EPOCH FROM ms.created_on) AS created_ts
            FROM map_submissions ms
            LEFT JOIN map_list_meta m
                ON ms.code = m.code
                AND m.created_on > ms.created_on
            WHERE m.code IS NULL
                " . ($omitRejected ? "AND ms.rejected_by IS NULL" : "") . "
            ORDER BY ms.created_on DESC
        ";

        $allSubmissions = DB::select($sql);

        $total = count($allSubmissions);
        $pages = (int) ceil($total / $perPage);

        // If no results or page is beyond available pages, return empty
        if ($total === 0 || $page > $pages) {
            return response()->json([
                'total' => 0,
                'pages' => 0,
                'submissions' => [],
            ]);
        }

        // Manual pagination
        $offset = ($page - 1) * $perPage;
        $pageSubmissions = array_slice($allSubmissions, $offset, $perPage);

        $results = [];
        foreach ($pageSubmissions as $sub) {
            $results[] = [
                'id' => $sub->id,
                'code' => $sub->code,
                'submitter' => (string) $sub->submitter_id,
                'subm_notes' => $sub->subm_notes,
                'format_id' => $sub->format_id,
                'proposed' => $sub->proposed,
                'rejected_by' => $sub->rejected_by ? (string) $sub->rejected_by : null,
                'created_on' => (int) $sub->created_ts,
                'completion_proof' => $sub->completion_proof,
            ];
        }

        return response()->json([
            'total' => $total,
            'pages' => $pages,
            'submissions' => $results,
        ]);
    }

    /**
     * Get a map by code, name, alias, or placement.
     *
     * @OA\Get(
     *     path="/maps/{code}",
     *     summary="Returns a map's data",
     *     tags={"Maps"},
     *     @OA\Parameter(name="code", in="path", required=true, schema=@OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Map data", @OA\JsonContent(
     *         @OA\Property(property="code", type="string"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="placement_curver", type="integer", nullable=true),
     *         @OA\Property(property="placement_allver", type="integer", nullable=true),
     *         @OA\Property(property="difficulty", type="integer", nullable=true),
     *         @OA\Property(property="botb_difficulty", type="integer", nullable=true),
     *         @OA\Property(property="remake_of", type="integer", nullable=true),
     *         @OA\Property(property="r6_start", type="string", nullable=true),
     *         @OA\Property(property="map_data", type="string", nullable=true),
     *         @OA\Property(property="optimal_heros", type="array", @OA\Items(type="string")),
     *         @OA\Property(property="deleted_on", type="string", nullable=true),
     *         @OA\Property(property="map_preview_url", type="string", nullable=true),
     *         @OA\Property(property="map_notes", type="string", nullable=true),
     *         @OA\Property(property="creators", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="additional_codes", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="verifications", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="is_verified", type="boolean"),
     *         @OA\Property(property="lccs", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="compatibilities", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="aliases", type="array", @OA\Items(type="string"))
     *     )),
     *     @OA\Response(response=404, description="No map with that ID was found")
     * )
     */
    public function show(string $code): JsonResponse
    {
        $searchCode = str_replace(' ', '_', $code);
        $timestamp = now();

        $map = DB::select("
            WITH config_values AS (
                SELECT
                    (SELECT value::int FROM config WHERE name='current_btd6_ver') AS current_btd6_ver
            ),
            verified_maps AS (
                SELECT v.map_code, COUNT(*) > 0 AS is_verified
                FROM verifications v
                CROSS JOIN config_values cv
                WHERE v.version = cv.current_btd6_ver
                GROUP BY v.map_code
            ),
            possible_map AS (
                SELECT * FROM (
                    SELECT 1 AS ord, m.code, m.name, m.r6_start, m.map_data, mlm.optimal_heros, m.map_preview_url, m.map_notes, mlm.botb_difficulty, mlm.remake_of,
                           mlm.placement_curver, mlm.placement_allver, mlm.difficulty, mlm.deleted_on
                    FROM maps m
                    JOIN latest_maps_meta(?) mlm ON m.code = mlm.code
                    WHERE m.code = ? AND mlm.deleted_on IS NULL

                    UNION

                    SELECT 2 AS ord, m.code, m.name, m.r6_start, m.map_data, mlm.optimal_heros, m.map_preview_url, m.map_notes, mlm.botb_difficulty, mlm.remake_of,
                           mlm.placement_curver, mlm.placement_allver, mlm.difficulty, mlm.deleted_on
                    FROM maps m
                    JOIN latest_maps_meta(?) mlm ON m.code = mlm.code
                    WHERE LOWER(m.name) = LOWER(?) AND mlm.deleted_on IS NULL

                    UNION

                    SELECT 4 AS ord, m.code, m.name, m.r6_start, m.map_data, mlm.optimal_heros, m.map_preview_url, m.map_notes, mlm.botb_difficulty, mlm.remake_of,
                           mlm.placement_curver, mlm.placement_allver, mlm.difficulty, mlm.deleted_on
                    FROM maps m
                    JOIN map_list_meta mlm ON m.code = mlm.code
                    JOIN map_aliases a ON m.code = a.map
                    WHERE LOWER(a.alias) = LOWER(?) OR LOWER(a.alias) = LOWER(?)

                    UNION

                    (
                        SELECT 6 AS ord, m.code, m.name, m.r6_start, m.map_data, mlm.optimal_heros, m.map_preview_url, m.map_notes, mlm.botb_difficulty, mlm.remake_of,
                               mlm.placement_curver, mlm.placement_allver, mlm.difficulty, mlm.deleted_on
                        FROM maps m
                        JOIN map_list_meta mlm ON m.code = mlm.code
                        WHERE m.code = ? AND mlm.deleted_on IS NOT NULL
                        ORDER BY mlm.created_on DESC
                        LIMIT 1
                    )

                    UNION

                    (
                        SELECT 7 AS ord, m.code, m.name, m.r6_start, m.map_data, mlm.optimal_heros, m.map_preview_url, m.map_notes, mlm.botb_difficulty, mlm.remake_of,
                               mlm.placement_curver, mlm.placement_allver, mlm.difficulty, mlm.deleted_on
                        FROM maps m
                        JOIN map_list_meta mlm ON m.code = mlm.code
                        WHERE LOWER(m.name) = LOWER(?) AND mlm.deleted_on IS NOT NULL
                        ORDER BY mlm.created_on DESC
                        LIMIT 1
                    )
                ) possible
                ORDER BY ord
                LIMIT 1
            )
            SELECT
                pm.code, pm.name, pm.placement_curver, pm.placement_allver, pm.difficulty,
                pm.r6_start, pm.map_data, v.is_verified, pm.deleted_on,
                pm.optimal_heros, pm.map_preview_url, pm.botb_difficulty,
                pm.remake_of, pm.map_notes
            FROM possible_map pm
            LEFT JOIN verified_maps v ON v.map_code = pm.code
        ", [
            $timestamp,
            $searchCode,
            $timestamp,
            $code,
            $searchCode,
            $code,
            $searchCode,
            $code,
        ]);

        if (empty($map)) {
            return response()->json(['error' => 'No map with that ID was found'], 404);
        }

        $map = $map[0];
        $mapCode = $map->code;

        // Fetch related data
        $lccs = DB::table('lccs_by_map')
            ->where('map', $mapCode)
            ->get()
            ->map(fn($lcc) => [
                'id' => $lcc->id,
                'format' => $lcc->format,
                'leftover' => $lcc->leftover,
            ])->toArray();

        $additionalCodes = DB::table('additional_codes')
            ->where('belongs_to', $mapCode)
            ->get()
            ->map(fn($ac) => [
                'code' => $ac->code,
                'description' => $ac->description,
            ])->toArray();

        $creators = DB::table('creators')
            ->join('users', 'creators.user_id', '=', 'users.discord_id')
            ->where('map_code', $mapCode)
            ->get()
            ->map(fn($c) => [
                'user_id' => (string) $c->user_id,
                'role' => $c->role,
                'name' => $c->name,
            ])->toArray();

        $verifications = DB::table('verifications')
            ->join('users', 'verifications.user_id', '=', 'users.discord_id')
            ->where('map_code', $mapCode)
            ->where(function ($q) {
                $q->whereNull('version')
                    ->orWhere('version', DB::raw('(SELECT value::int FROM config WHERE name=\'current_btd6_ver\')'));
            })
            ->orderByRaw('version ASC NULLS FIRST')
            ->get()
            ->map(fn($v) => [
                'user_id' => (string) $v->user_id,
                'version' => $v->version ? $v->version / 10 : null,
                'name' => $v->name,
            ])->toArray();

        $compatibilities = DB::table('mapver_compatibilities')
            ->where('map_code', $mapCode)
            ->get()
            ->map(fn($c) => [
                'status' => $c->status,
                'version' => $c->version,
            ])->toArray();

        $aliases = DB::table('map_aliases')
            ->where('map_code', $mapCode)
            ->pluck('alias')
            ->toArray();

        $optimalHeroes = $map->optimal_heros ? explode(';', $map->optimal_heros) : [];

        return response()->json([
            'code' => $map->code,
            'name' => $map->name,
            'placement_curver' => $map->placement_curver,
            'placement_allver' => $map->placement_allver,
            'difficulty' => $map->difficulty,
            'botb_difficulty' => $map->botb_difficulty,
            'remake_of' => $map->remake_of,
            'r6_start' => $map->r6_start,
            'map_data' => $map->map_data,
            'deleted_on' => $map->deleted_on,
            'optimal_heros' => $optimalHeroes,
            'map_preview_url' => $map->map_preview_url,
            'map_notes' => $map->map_notes,
            'creators' => $creators,
            'additional_codes' => $additionalCodes,
            'verifications' => $verifications,
            'is_verified' => $map->is_verified,
            'lccs' => $lccs,
            'compatibilities' => $compatibilities,
            'aliases' => $aliases,
        ]);
    }

    /**
     * Get current user's completions on a map.
     *
     * @OA\Get(
     *     path="/maps/{code}/completions/@me",
     *     summary="Returns the authenticated user's completions on the specified map",
     *     tags={"Completions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="code", in="path", required=true, schema=@OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Array of ListCompletion", @OA\JsonContent(type="array", @OA\Items(
     *         @OA\Property(property="id", type="integer"),
     *         @OA\Property(property="map", type="string"),
     *         @OA\Property(property="users", type="array", @OA\Items(type="string")),
     *         @OA\Property(property="black_border", type="boolean"),
     *         @OA\Property(property="no_geraldo", type="boolean"),
     *         @OA\Property(property="current_lcc", type="boolean"),
     *         @OA\Property(property="format", type="integer"),
     *         @OA\Property(property="lcc", type="object", nullable=true),
     *         @OA\Property(property="subm_proof_img", type="array", @OA\Items(type="string")),
     *         @OA\Property(property="subm_proof_vid", type="array", @OA\Items(type="string")),
     *         @OA\Property(property="subm_notes", type="string", nullable=true)
     *     ))),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Map not found")
     * )
     */
    public function myCompletions(string $code): JsonResponse
    {
        // Check map exists
        $map = Map::find($code);
        if (!$map) {
            return response()->json(['error' => 'No map with that ID was found'], 404);
        }

        $userId = auth()->guard('discord')->user()->discord_id;
        $formatIds = [1, 51];

        $completions = Completion::with([
            'proofs',
            'map.latestMeta',
            'latestMeta.format',
            'latestMeta.players',
            'latestMeta.lcc',
        ])
            ->where('map_code', $code)
            ->whereHas('latestMeta', function ($query) use ($formatIds, $userId) {
                $query->whereNull('deleted_on')
                    ->whereNotNull('accepted_by_id')
                    ->whereIn('format_id', $formatIds)
                    ->whereHas('players', function ($q) use ($userId) {
                        $q->where('discord_id', $userId);
                    });
            })
            ->orderByDesc('submitted_on')
            ->orderByDesc('id')
            ->get();

        // Single query to get all current LCCs
        $mapCodes = $completions->pluck('map.code');
        $lccIds = $completions->pluck('latestMeta.lcc_id')->filter();
        $currentLccs = DB::table('lccs_by_map')
            ->whereIn('map', $mapCodes)
            ->whereIn('id', $lccIds)
            ->pluck('id', 'map');

        $results = [];
        foreach ($completions as $completion) {
            $meta = $completion->latestMeta;
            $currentLcc = isset($currentLccs[$completion->map->code]) &&
                $currentLccs[$completion->map->code] === $meta->lcc_id;

            $results[] = [
                'id' => $completion->id,
                'map' => $completion->map_code,
                'users' => $meta->players
                    ->map(fn($user) => (string) $user->discord_id)
                    ->values()
                    ->unique()
                    ->toArray(),
                'black_border' => $meta->black_border,
                'no_geraldo' => $meta->no_geraldo,
                'current_lcc' => $currentLcc,
                'format' => $meta->format_id,
                'lcc' => $meta->lcc_id ? [
                    'id' => $meta->lcc_id,
                    'leftover' => $meta->lcc->leftover ?? null,
                ] : null,
                'subm_proof_img' => array_values(array_filter($completion->subm_proof_img ?? [])),
                'subm_proof_vid' => array_values(array_filter($completion->subm_proof_vid ?? [])),
                'subm_notes' => $completion->subm_notes,
            ];
        }

        return response()->json($results);
    }

    /**
     * Get completions for a map.
     *
     * @OA\Get(
     *     path="/maps/{code}/completions",
     *     summary="Returns a list of up to 50 maplist completions for this map",
     *     tags={"Completions"},
     *     @OA\Parameter(name="code", in="path", required=true, schema=@OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", schema=@OA\Schema(type="integer")),
     *     @OA\Parameter(name="formats", in="query", schema=@OA\Schema(type="string", example="1,51")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated completions",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="pages", type="integer"),
     *             @OA\Property(property="completions", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="map", type="string"),
     *                 @OA\Property(property="users", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="black_border", type="boolean"),
     *                 @OA\Property(property="no_geraldo", type="boolean"),
     *                 @OA\Property(property="current_lcc", type="boolean"),
     *                 @OA\Property(property="format", type="integer"),
     *                 @OA\Property(property="lcc", type="object", nullable=true),
     *                 @OA\Property(property="subm_proof_img", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="subm_proof_vid", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="subm_notes", type="string", nullable=true)
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Map not found")
     * )
     */
    public function completions(MapCompletionsRequest $request, string $code): JsonResponse
    {
        // Check map exists
        $map = Map::find($code);
        if (!$map) {
            return response()->json(['error' => 'No map with that ID was found'], 404);
        }

        $page = max(1, $request->input('page', 1));
        $formats = $request->getParsedFormats();
        $formatIds = !empty($formats) ? $formats : [1, 51];
        $perPage = 50;

        $paginator = Completion::with([
            'proofs',
            'map.latestMeta',
            'latestMeta.format',
            'latestMeta.players',
            'latestMeta.lcc',
        ])
            ->where('map_code', $code)
            ->whereHas('latestMeta', function ($query) use ($formatIds) {
                $query->whereNull('deleted_on')
                    ->whereNotNull('accepted_by_id')
                    ->whereIn('format_id', $formatIds);
            })
            ->orderByDesc('submitted_on')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        // If page is beyond the last page and there are no results, return empty
        if ($paginator->total() === 0) {
            return response()->json([
                'total' => 0,
                'pages' => 0,
                'completions' => [],
            ]);
        }

        // Single query to get all current LCCs
        $completions = $paginator->items();
        $mapCodes = collect($completions)->pluck('map.code');
        $lccIds = collect($completions)->pluck('latestMeta.lcc_id')->filter();
        $currentLccs = DB::table('lccs_by_map')
            ->whereIn('map', $mapCodes)
            ->whereIn('id', $lccIds)
            ->pluck('id', 'map');

        $results = [];
        foreach ($completions as $completion) {
            $meta = $completion->latestMeta;
            $currentLcc = isset($currentLccs[$completion->map->code]) &&
                $currentLccs[$completion->map->code] === $meta->lcc_id;

            $results[] = [
                'id' => $completion->id,
                'map' => $completion->map_code,
                'users' => $meta->players
                    ->map(fn($user) => [
                        'id' => (string) $user->discord_id,
                        'name' => $user->name,
                    ])
                    ->values()
                    ->toArray(),
                'black_border' => $meta->black_border,
                'no_geraldo' => $meta->no_geraldo,
                'current_lcc' => $currentLcc,
                'format' => $meta->format_id,
                'lcc' => $meta->lcc_id ? [
                    'id' => $meta->lcc_id,
                    'leftover' => $meta->lcc->leftover ?? null,
                ] : null,
                'subm_proof_img' => array_values(array_filter($completion->subm_proof_img ?? [])),
                'subm_proof_vid' => array_values(array_filter($completion->subm_proof_vid ?? [])),
                'subm_notes' => $completion->subm_notes,
            ];
        }

        return response()->json([
            'total' => $paginator->total(),
            'pages' => $paginator->lastPage(),
            'completions' => $results,
        ]);
    }

    // Helper methods for format-specific map queries

    protected function getListMaps(bool $curver): JsonResponse
    {
        $placementField = $curver ? 'placement_curver' : 'placement_allver';

        // Get config values
        $config = Config::loadVars(['current_btd6_ver', 'map_count']);
        $currentBtd6Ver = $config->get('current_btd6_ver');
        $mapCount = $config->get('map_count');

        // Query metas with map relationship
        $metas = MapListMeta::with('map')
            ->whereNull('deleted_on')
            ->whereBetween($placementField, [1, $mapCount])
            ->orderBy($placementField)
            ->get();

        // Get verified map codes (only for these maps)
        $mapCodes = $metas->pluck('code');
        $verifiedMapCodes = Verification::getVerifiedMapCodes($currentBtd6Ver, $mapCodes);

        // Format response
        return response()->json($metas->map(function ($meta) use ($placementField, $verifiedMapCodes) {
            $map = $meta->map;
            return [
                'name' => $map->name,
                'code' => $map->code,
                'placement' => $meta->{$placementField},
                'is_verified' => $verifiedMapCodes->contains($map->code),
                'map_preview_url' => $map->map_preview_url,
            ];
        })->values());
    }

    protected function getMapsByIdx(string $idx, ?int $filterVal): JsonResponse
    {
        // Get current BTD6 version
        $config = Config::loadVars(['current_btd6_ver']);
        $currentBtd6Ver = $config->get('current_btd6_ver');

        // Query metas with map relationship
        $query = MapListMeta::with('map')
            ->whereNull('deleted_on')
            ->whereNotNull($idx)
            ->orderBy($idx);

        if ($filterVal !== null) {
            $query->where($idx, $filterVal);
        }

        $metas = $query->get();

        // Get verified map codes (only for these maps)
        $mapCodes = $metas->pluck('code');
        $verifiedMapCodes = Verification::getVerifiedMapCodes($currentBtd6Ver, $mapCodes);

        // Format response
        return response()->json($metas->map(function ($meta) use ($idx, $verifiedMapCodes) {
            $map = $meta->map;
            return [
                'name' => $map->name,
                'code' => $map->code,
                'placement' => $meta->{$idx},
                'is_verified' => $verifiedMapCodes->contains($map->code),
                'map_preview_url' => $map->map_preview_url,
            ];
        })->values());
    }

    protected function getNostalgiaPack(?int $filterVal): JsonResponse
    {
        if ($filterVal === null) {
            return response()->json(['error' => 'Filter is required for this format'], 400);
        }

        // Get current BTD6 version
        $config = Config::loadVars(['current_btd6_ver']);
        $currentBtd6Ver = $config->get('current_btd6_ver');

        // Query map metas that are remakes of retro maps in the specified game
        $metas = MapListMeta::with(['map', 'retroMap.game'])
            ->whereHas('retroMap', function ($query) use ($filterVal) {
                $query->where('retro_game_id', $filterVal);
            })
            ->join('retro_maps', 'map_list_meta.remake_of', '=', 'retro_maps.id')
            ->whereNull('map_list_meta.deleted_on')
            ->orderBy('retro_maps.sort_order')
            ->select('map_list_meta.*')
            ->get();

        // Get verified map codes
        $mapCodes = $metas->pluck('code');
        $verifiedMapCodes = Verification::getVerifiedMapCodes($currentBtd6Ver, $mapCodes);

        // Format response
        return response()->json($metas->map(function ($meta) use ($verifiedMapCodes) {
            $retroMap = $meta->retroMap;
            $map = $meta->map;

            return [
                'name' => $retroMap->name,
                'code' => $map->code,
                'placement' => [
                    'id' => $retroMap->id,
                    'name' => $retroMap->name,
                    'game_name' => $retroMap->game->game_name,
                    'category_name' => $retroMap->game->category_name,
                ],
                'is_verified' => $verifiedMapCodes->contains($map->code),
                'map_preview_url' => $map->map_preview_url,
            ];
        })->values());
    }
}
