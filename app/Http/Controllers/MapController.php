<?php

namespace App\Http\Controllers;

use App\Constants\FormatConstants;
use App\Http\Requests\Map\IndexMapRequest;
use App\Http\Requests\Map\StoreMapRequest;
use App\Http\Requests\Map\UpdateMapRequest;
use App\Models\Config;
use App\Models\Creator;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\Verification;
use App\Services\MapService;
use App\Services\UserService;
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

    /**
     * Get a single map by ID.
     *
     * @OA\Get(
     *     path="/maps/{id}",
     *     summary="Get a single map",
     *     description="Retrieves a single map with its metadata active at the specified timestamp.",
     *     tags={"Maps"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The map code",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="timestamp",
     *         in="query",
     *         required=false,
     *         description="Unix timestamp to query the map's metadata at. Defaults to current time.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         required=false,
     *         description="Comma-separated list of additional data to include. Use 'creators.flair' to include creator avatar and banner URLs from Ninja Kiwi, 'verifiers.flair' to include verifier avatar and banner URLs from Ninja Kiwi.",
     *         @OA\Schema(type="string", example="creators.flair,verifiers.flair")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(ref="#/components/schemas/Map")
     *     ),
     *     @OA\Response(response=404, description="Map not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function show(Request $request, $id, UserService $userService)
    {
        $validated = $request->validate([
            'timestamp' => 'nullable|integer',
            'include' => 'nullable|string',
        ]);

        $timestamp = Carbon::createFromTimestamp($validated['timestamp'] ?? Carbon::now()->unix());
        $include = array_filter(explode(',', $validated['include'] ?? ''));
        $includeCreatorsFlair = in_array('creators.flair', $include);
        $includeVerifiersFlair = in_array('verifiers.flair', $include);

        // Get the map with eager loaded relationships
        $map = Map::with('creators.user', 'verifications.user')->find($id);
        if (!$map) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Get active MapListMeta at timestamp
        $latestMetaCte = MapListMeta::activeAtTimestamp($timestamp);
        $meta = MapListMeta::from(DB::raw("({$latestMetaCte->toSql()}) as map_list_meta"))
            ->setBindings($latestMetaCte->getBindings())
            ->with(['retroMap.game'])
            ->where('code', $map->code)
            ->first();

        if (!$meta) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Get current BTD6 version for verifications
        $currentBtd6Ver = Config::loadVars(['current_btd6_ver'])->get('current_btd6_ver');

        // Build result
        $result = [
            ...$map->toArray(),
            ...$meta->toArray(),
            'verifications' => $map->verifications->filter(function ($v) use ($currentBtd6Ver) {
                return $v->version === null || $v->version === $currentBtd6Ver;
            }),
        ];
        $result['is_verified'] = $result['verifications']->isNotEmpty();

        // Load creator flair if requested
        if ($includeCreatorsFlair) {
            $result['creators'] = $map->creators->map(function ($creator) use ($userService) {
                $user = $creator->user;
                $deco = null;
                if ($user && $user->nk_oak) {
                    $deco = $userService->getUserDeco($user->nk_oak);
                }

                return [
                    ...$creator->toArray(),
                    'avatar_url' => $deco['avatar_url'] ?? null,
                    'banner_url' => $deco['banner_url'] ?? null,
                ];
            });
        }

        // Load verification flair if requested
        if ($includeVerifiersFlair) {
            $result['verifications'] = $result['verifications']->map(function ($verification) use ($userService) {
                $user = $verification->user;
                $deco = null;
                if ($user && $user->nk_oak) {
                    $deco = $userService->getUserDeco($user->nk_oak);
                }

                return [
                    ...$verification->toArray(),
                    'avatar_url' => $deco['avatar_url'] ?? null,
                    'banner_url' => $deco['banner_url'] ?? null,
                ];
            });
        }

        return response()->json($result);
    }

    /**
     * Store a newly created map in storage.
     *
     * @OA\Post(
     *     path="/maps",
     *     summary="Create a new map",
     *     description="Creates a new map with its metadata. Fields that require specific permissions will be silently ignored if the user lacks those permissions. User must have at least one format's edit:map permission.",
     *     tags={"Maps"},
     *     security={{"discord_auth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/StoreMapRequest")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Map created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Map")
     *     ),
     *     @OA\Response(response=403, description="Forbidden - User lacks edit:map permission"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function save(StoreMapRequest $request, MapService $mapService)
    {
        $now = Carbon::now();
        $user = auth()->guard('discord')->user();

        // Get user's formats with edit:map permission
        $userFormatIds = $user->formatsWithPermission('edit:map');

        // User must have at least one edit:map permission
        if (empty($userFormatIds)) {
            return response()->json(['message' => 'Forbidden - You do not have permission to create maps'], 403);
        }

        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $userFormatIds, $mapService, $now) {
            // Filter meta fields based on user permissions
            $metaFields = $this->filterMetaFieldsByPermissions(
                $validated,
                $userFormatIds,
                null  // POST: non-permitted fields → NULL
            );

            // Create the Map
            $map = Map::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'r6_start' => $validated['r6_start'] ?? null,
                'map_data' => $validated['map_data'] ?? null,
                'map_preview_url' => $validated['map_preview_url'] ?? null,
                'map_notes' => $validated['map_notes'] ?? null,
            ]);

            // Create MapListMeta
            $meta = MapListMeta::create([
                'code' => $map->code,
                'placement_curver' => $metaFields['placement_curver'] ?? null,
                'placement_allver' => $metaFields['placement_allver'] ?? null,
                'difficulty' => $metaFields['difficulty'] ?? null,
                'optimal_heros' => $validated['optimal_heros'] ?? [],
                'botb_difficulty' => $metaFields['botb_difficulty'] ?? null,
                'remake_of' => $metaFields['remake_of'] ?? null,
                'created_on' => $now,
                'deleted_on' => null,
            ]);

            // Handle reranking if placements were set
            $hasGlobalPermission = in_array(null, $userFormatIds, true);
            $hasMaplistPermission = $hasGlobalPermission || in_array(FormatConstants::MAPLIST, $userFormatIds);
            $hasMaplistAllPermission = $hasGlobalPermission || in_array(FormatConstants::MAPLIST_ALL_VERSIONS, $userFormatIds);

            $curPositionFrom = null;
            $curPositionTo = $hasMaplistPermission ? ($metaFields['placement_curver'] ?? null) : null;
            $allPositionFrom = null;
            $allPositionTo = $hasMaplistAllPermission ? ($metaFields['placement_allver'] ?? null) : null;

            $mapService->rerankPlacements(
                $curPositionFrom,
                $curPositionTo,
                $allPositionFrom,
                $allPositionTo,
                $map->code,
                $now
            );

            // Handle remake_of cleanup
            if (isset($metaFields['remake_of']) && $metaFields['remake_of'] !== null) {
                $mapService->clearPreviousRemakeOf($metaFields['remake_of'], $map->code, $now);
            }

            // Create creators if provided
            if (isset($validated['creators']) && is_array($validated['creators'])) {
                foreach ($validated['creators'] as $userId) {
                    Creator::create([
                        'map_code' => $map->code,
                        'user_id' => $userId,
                    ]);
                }
            }

            // Create verifiers if provided
            if (isset($validated['verifiers']) && is_array($validated['verifiers'])) {
                foreach ($validated['verifiers'] as $userId) {
                    Verification::create([
                        'map_code' => $map->code,
                        'user_id' => $userId,
                        'version' => null,  // Version-less verifiers
                    ]);
                }
            }

            // Load fresh data for response
            $map->refresh();
            $meta->refresh();
            $meta->load('retroMap.game');

            // Build response
            $result = [
                ...$map->toArray(),
                ...$meta->toArray(),
                'is_verified' => false,  // New maps are not verified
            ];

            return response()->json($result, 201);
        });
    }

    public function submit(Request $request)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    /**
     * Permission to field mapping for MapListMeta
     */
    private function getPermissionFieldMapping(): array
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
    private function filterMetaFieldsByPermissions(
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
                    // POST: Set to null (already filtered by not adding to array)
                }
            }
        }

        return $filtered;
    }

    /**
     * Update the specified map in storage.
     *
     * @OA\Put(
     *     path="/maps/{id}",
     *     summary="Update a map",
     *     description="Updates a map and its metadata. Fields that require specific permissions will retain their existing values if the user lacks those permissions. User must have at least one format's edit:map permission.",
     *     tags={"Maps"},
     *     security={{"discord_auth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The map code",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/UpdateMapRequest")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Map updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Map")
     *     ),
     *     @OA\Response(response=403, description="Forbidden - User lacks edit:map permission"),
     *     @OA\Response(response=404, description="Map not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateMapRequest $request, $id, MapService $mapService)
    {
        $now = Carbon::now();
        $user = auth()->guard('discord')->user();

        // Get user's formats with edit:map permission
        $userFormatIds = $user->formatsWithPermission('edit:map');

        // User must have at least one edit:map permission
        if (empty($userFormatIds)) {
            return response()->json(['message' => 'Forbidden - You do not have permission to edit maps'], 403);
        }

        // Find the map
        $map = Map::find($id);
        if (!$map) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Get current active MapListMeta
        $latestMetaCte = MapListMeta::activeAtTimestamp($now);
        $existingMeta = MapListMeta::from(DB::raw("({$latestMetaCte->toSql()}) as map_list_meta"))
            ->setBindings($latestMetaCte->getBindings())
            ->where('code', $map->code)
            ->first();

        if (!$existingMeta) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $map, $existingMeta, $userFormatIds, $mapService, $now) {
            // Filter meta fields based on user permissions
            $metaFields = $this->filterMetaFieldsByPermissions(
                $validated,
                $userFormatIds,
                $existingMeta  // PUT: non-permitted fields → use existing values
            );

            // Check if any permission-based field actually changed
            $metaFieldsChanged = false;
            $permissionFields = $this->getPermissionFieldMapping();

            foreach ($permissionFields as $field) {
                $oldValue = $existingMeta->$field;
                $newValue = $metaFields[$field] ?? null;
                if ($oldValue !== $newValue) {
                    $metaFieldsChanged = true;
                    break;
                }
            }

            // Update Map fields if provided
            if (isset($validated['code'])) {
                $map->code = $validated['code'];
            }
            if (isset($validated['name'])) {
                $map->name = $validated['name'];
            }
            if (array_key_exists('r6_start', $validated)) {
                $map->r6_start = $validated['r6_start'];
            }
            if (array_key_exists('map_data', $validated)) {
                $map->map_data = $validated['map_data'];
            }
            if (array_key_exists('map_preview_url', $validated)) {
                $map->map_preview_url = $validated['map_preview_url'];
            }
            if (array_key_exists('map_notes', $validated)) {
                $map->map_notes = $validated['map_notes'];
            }
            $map->save();

            // Handle creators update
            if (array_key_exists('creators', $validated)) {
                // Delete existing creators
                Creator::where('map_code', $map->code)->delete();

                // Create new creators
                if (is_array($validated['creators'])) {
                    foreach ($validated['creators'] as $userId) {
                        Creator::create([
                            'map_code' => $map->code,
                            'user_id' => $userId,
                        ]);
                    }
                }
            }

            // Handle verifiers update
            if (array_key_exists('verifiers', $validated)) {
                // Delete existing version-less verifiers
                Verification::where('map_code', $map->code)
                    ->whereNull('version')
                    ->delete();

                // Create new verifiers
                if (is_array($validated['verifiers'])) {
                    foreach ($validated['verifiers'] as $userId) {
                        Verification::create([
                            'map_code' => $map->code,
                            'user_id' => $userId,
                            'version' => null,
                        ]);
                    }
                }
            }

            // Create new MapListMeta only if permission-based fields changed
            // or if optimal_heros changed (not permission-restricted)
            $optimalHeroesChanged = isset($validated['optimal_heros'])
                && $existingMeta->optimal_heros !== $validated['optimal_heros'];

            if ($metaFieldsChanged || $optimalHeroesChanged) {
                // Handle reranking if placements changed
                $hasGlobalPermission = in_array(null, $userFormatIds, true);
                $hasMaplistPermission = $hasGlobalPermission || in_array(FormatConstants::MAPLIST, $userFormatIds);
                $hasMaplistAllPermission = $hasGlobalPermission || in_array(FormatConstants::MAPLIST_ALL_VERSIONS, $userFormatIds);

                $curPositionFrom = $hasMaplistPermission ? $existingMeta->placement_curver : null;
                $curPositionTo = $hasMaplistPermission ? ($metaFields['placement_curver'] ?? $existingMeta->placement_curver) : null;
                $allPositionFrom = $hasMaplistAllPermission ? $existingMeta->placement_allver : null;
                $allPositionTo = $hasMaplistAllPermission ? ($metaFields['placement_allver'] ?? $existingMeta->placement_allver) : null;

                $mapService->rerankPlacements(
                    $curPositionFrom,
                    $curPositionTo,
                    $allPositionFrom,
                    $allPositionTo,
                    $map->code,
                    $now
                );

                // Handle remake_of cleanup if changed
                $oldRemakeOf = $existingMeta->remake_of;
                $newRemakeOf = $metaFields['remake_of'] ?? $existingMeta->remake_of;

                if ($oldRemakeOf !== $newRemakeOf && $newRemakeOf !== null) {
                    $mapService->clearPreviousRemakeOf($newRemakeOf, $map->code, $now);
                }

                // Create new MapListMeta
                $meta = MapListMeta::create([
                    'code' => $map->code,
                    'placement_curver' => $metaFields['placement_curver'] ?? $existingMeta->placement_curver,
                    'placement_allver' => $metaFields['placement_allver'] ?? $existingMeta->placement_allver,
                    'difficulty' => $metaFields['difficulty'] ?? $existingMeta->difficulty,
                    'optimal_heros' => $validated['optimal_heros'] ?? $existingMeta->optimal_heros,
                    'botb_difficulty' => $metaFields['botb_difficulty'] ?? $existingMeta->botb_difficulty,
                    'remake_of' => $metaFields['remake_of'] ?? $existingMeta->remake_of,
                    'created_on' => $now,
                    'deleted_on' => null,
                ]);
            } else {
                $meta = $existingMeta;
            }

            // Load fresh data for response
            $map->refresh();
            if ($metaFieldsChanged || $optimalHeroesChanged) {
                $meta->refresh();
            }
            $meta->load('retroMap.game');

            // Check if map is verified
            $currentBtd6Ver = Config::loadVars(['current_btd6_ver'])->get('current_btd6_ver');
            $isVerified = Verification::where('map_code', $map->code)
                ->where(function ($q) use ($currentBtd6Ver) {
                    $q->where('version', $currentBtd6Ver)
                        ->orWhereNull('version');
                })
                ->exists();

            // Build response
            $result = [
                ...$map->toArray(),
                ...$meta->toArray(),
                'is_verified' => $isVerified,
            ];

            return response()->json($result, 200);
        });
    }

    /**
     * Remove the specified map from storage (soft delete via MapListMeta).
     *
     * @OA\Delete(
     *     path="/maps/{id}",
     *     summary="Delete a map",
     *     description="Soft-deletes a map by setting its metadata fields to NULL based on user permissions. If ALL list fields (placement_curver, placement_allver, difficulty, botb_difficulty, remake_of) are NULL, also sets deleted_on. User must have at least one format's edit:map permission.",
     *     tags={"Maps"},
     *     security={{"discord_auth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The map code",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Map deleted successfully"
     *     ),
     *     @OA\Response(response=403, description="Forbidden - User lacks edit:map permission"),
     *     @OA\Response(response=404, description="Map not found")
     * )
     */
    public function destroy($id, MapService $mapService)
    {
        $now = Carbon::now();
        $user = auth()->guard('discord')->user();

        // Get user's formats with edit:map permission
        $userFormatIds = $user->formatsWithPermission('edit:map');

        // User must have at least one edit:map permission
        if (empty($userFormatIds)) {
            return response()->json(['message' => 'Forbidden - You do not have permission to delete maps'], 403);
        }

        // Get current active MapListMeta
        $latestMetaCte = MapListMeta::activeAtTimestamp($now);
        $existingMeta = MapListMeta::from(DB::raw("({$latestMetaCte->toSql()}) as map_list_meta"))
            ->setBindings($latestMetaCte->getBindings())
            ->where('code', $id)
            ->first();

        if (!$existingMeta) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return DB::transaction(function () use ($id, $existingMeta, $userFormatIds, $mapService, $now) {
            // Build new meta data based on user's permissions
            // All permission-based fields are set to NULL
            // null in userFormatIds grants admin privileges (all permissions)
            $hasGlobalPermission = in_array(null, $userFormatIds, true);
            $hasMaplistPermission = $hasGlobalPermission || in_array(FormatConstants::MAPLIST, $userFormatIds);
            $hasMaplistAllPermission = $hasGlobalPermission || in_array(FormatConstants::MAPLIST_ALL_VERSIONS, $userFormatIds);
            $hasExpertListPermission = $hasGlobalPermission || in_array(FormatConstants::EXPERT_LIST, $userFormatIds);
            $hasBotbPermission = $hasGlobalPermission || in_array(FormatConstants::BEST_OF_THE_BEST, $userFormatIds);
            $hasNostalgiaPermission = $hasGlobalPermission || in_array(FormatConstants::NOSTALGIA_PACK, $userFormatIds);

            $newPlacementCurver = $hasMaplistPermission ? null : $existingMeta->placement_curver;
            $newPlacementAllver = $hasMaplistAllPermission ? null : $existingMeta->placement_allver;
            $newDifficulty = $hasExpertListPermission ? null : $existingMeta->difficulty;
            $newBotbDifficulty = $hasBotbPermission ? null : $existingMeta->botb_difficulty;
            $newRemakeOf = $hasNostalgiaPermission ? null : $existingMeta->remake_of;

            // Check if ALL 5 list fields are NULL
            $allListFieldsNull = (
                $newPlacementCurver === null &&
                $newPlacementAllver === null &&
                $newDifficulty === null &&
                $newBotbDifficulty === null &&
                $newRemakeOf === null
            );

            // Set deleted_on if ALL list fields are NULL
            $newDeletedOn = $allListFieldsNull ? $now : null;

            // Handle reranking if placements are being cleared
            $curPositionFrom = $hasMaplistPermission ? $existingMeta->placement_curver : null;
            $allPositionFrom = $hasMaplistAllPermission ? $existingMeta->placement_allver : null;

            if (
                !($existingMeta->placement_curver !== $newPlacementCurver
                    || $existingMeta->placement_allver !== $newPlacementAllver
                    || $existingMeta->difficulty !== $newDifficulty
                    || $existingMeta->botb_difficulty !== $newBotbDifficulty
                    || $existingMeta->remake_of !== $newRemakeOf)
            ) {
                return response()->noContent();
            }

            $mapService->rerankPlacements(
                $curPositionFrom,
                null,
                $allPositionFrom,
                null,
                $id,
                $now
            );

            // Handle remake_of cleanup if being cleared
            if ($hasNostalgiaPermission && $existingMeta->remake_of !== null) {
                $mapService->clearPreviousRemakeOf($existingMeta->remake_of, $id, $now);
            }

            // Create new MapListMeta
            MapListMeta::create([
                'code' => $id,
                'placement_curver' => $newPlacementCurver,
                'placement_allver' => $newPlacementAllver,
                'difficulty' => $newDifficulty,
                'optimal_heros' => $existingMeta->optimal_heros,
                'botb_difficulty' => $newBotbDifficulty,
                'remake_of' => $newRemakeOf,
                'created_on' => $now,
                'deleted_on' => $newDeletedOn,
            ]);

            return response()->noContent();
        });
    }
}
