<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertCompletionRequest;
use App\Jobs\UpdateDiscordWebhookJob;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\LeastCostChimps;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Completions",
 *     description="Completion endpoints"
 * )
 */
class CompletionController extends Controller
{
    public function __construct(
        private VerificationService $verificationService
    ) {
    }

    /**
     * Get a specific completion by ID.
     *
     * @OA\Get(
     *     path="/completions/{cid}",
     *     summary="Get a completion by ID",
     *     tags={"Completions"},
     *     @OA\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="Completion ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(ref="#/components/schemas/Completion")
     *     ),
     *     @OA\Response(response=404, description="Completion not found")
     * )
     */
    public function show(int $cid): JsonResponse
    {
        $completion = Completion::with([
            'proofs',
            'map.latestMeta',
            'latestMeta.format',
            'latestMeta.players',
            'latestMeta.lcc',
            'latestMeta.acceptedBy',
        ])
            ->find($cid);

        if (!$completion) {
            return response()->json(['error' => 'Completion not found'], 404);
        }

        $meta = $completion->latestMeta;
        if (!$meta) {
            return response()->json(['error' => 'Completion metadata not found'], 404);
        }

        // Check if this is the current LCC using the lccs_by_map view
        $currentLcc = DB::table('lccs_by_map')
            ->where('map', $completion->map->code)
            ->where('id', $meta->lcc_id)
            ->exists();

        return response()->json($this->formatCompletionResponse($completion, $meta, $currentLcc));
    }

    /**
     * Edit a completion.
     *
     * @OA\Put(
     *     path="/completions/{cid}",
     *     summary="Edit a completion",
     *     tags={"Completions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="Completion ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(ref="#/components/schemas/UpsertCompletionRequest"),
     *     @OA\Response(response=204, description="Completion updated successfully"),
     *     @OA\Response(response=403, description="Forbidden - insufficient permissions"),
     *     @OA\Response(response=404, description="Completion not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpsertCompletionRequest $request, int $cid): JsonResponse
    {
        $user = auth()->guard('discord')->user();

        // Get the current completion metadata
        $currentMeta = CompletionMeta::where('completion_id', $cid)
            ->orderBy('created_on', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$currentMeta) {
            return response()->json(['error' => 'Completion not found'], 404);
        }

        // Check if user is trying to edit their own completion
        $userIds = $request->input('user_ids');
        if (in_array((string) $user->discord_id, $userIds)) {
            return response()->json(['error' => 'Cannot edit your own completion'], 403);
        }

        $newFormat = $request->input('format');
        $oldFormat = $currentMeta->format_id;

        // Check permissions for both formats in a single query
        $allowedFormats = $user->formatsWithPermission('edit:completion');
        if (!in_array(null, $allowedFormats, true) && (!in_array($newFormat, $allowedFormats) || !in_array($oldFormat, $allowedFormats))) {
            $missing = array_diff([$oldFormat, $newFormat], $allowedFormats);
            return response()->json(['error' => "Missing edit:completion permission for format(s): " . implode(', ', $missing)], 403);
        }

        DB::transaction(function () use ($request, $cid, $userIds, $currentMeta, $user) {
            // Handle LCC
            $lccId = null;
            if ($request->has('lcc') && $request->input('lcc') !== null) {
                $lcc = LeastCostChimps::create([
                    'leftover' => $request->input('lcc.leftover'),
                ]);
                $lccId = $lcc->id;
            }

            // Create new metadata version
            $newMeta = CompletionMeta::create([
                'completion_id' => $cid,
                'black_border' => $request->input('black_border'),
                'no_geraldo' => $request->input('no_geraldo'),
                'lcc_id' => $lccId,
                'created_on' => now(),
                'accepted_by_id' => $currentMeta->accepted_by_id,
                'format_id' => $request->input('format'),
            ]);

            // Sync players
            $newMeta->players()->sync($userIds);

            Log::info('Completion updated', [
                'completion_id' => $cid,
                'updated_by' => $user->discord_id,
            ]);
        });

        return response()->json([], 204);
    }

    /**
     * Delete (soft delete) a completion.
     *
     * @OA\Delete(
     *     path="/completions/{cid}",
     *     summary="Delete a completion",
     *     tags={"Completions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="Completion ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Completion deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden - insufficient permissions"),
     *     @OA\Response(response=404, description="Completion not found")
     * )
     */
    public function destroy(int $cid): JsonResponse
    {
        // Check for latest meta (including deleted)
        $anyMeta = CompletionMeta::where('completion_id', $cid)
            ->latest('created_on')
            ->first();
        if (!$anyMeta) {
            return response()->json(['error' => 'Completion not found'], 404);
        }

        // Check permission
        $user = auth()->guard('discord')->user();
        if (!$user->hasPermission('delete:completion', $anyMeta->format_id)) {
            return response()->json(['error' => "Missing delete:completion permission for format {$anyMeta->format_id}"], 403);
        }

        // If already deleted, return 204 (idempotent)
        if ($anyMeta->deleted_on === null) {
            $anyMeta->update(['deleted_on' => now()]);

            Log::info('Completion deleted', [
                'completion_id' => $cid,
                'deleted_by' => $user->discord_id,
            ]);
        }

        return response()->json([], 204);
    }

    /**
     * Accept a completion.
     *
     * @OA\Put(
     *     path="/completions/{cid}/accept",
     *     summary="Accept a completion",
     *     tags={"Completions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="Completion ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(ref="#/components/schemas/UpsertCompletionRequest"),
     *     @OA\Response(response=204, description="Completion accepted successfully"),
     *     @OA\Response(response=400, description="Completion already accepted"),
     *     @OA\Response(response=403, description="Forbidden - insufficient permissions"),
     *     @OA\Response(response=404, description="Completion not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function accept(UpsertCompletionRequest $request, int $cid): JsonResponse
    {
        $user = auth()->guard('discord')->user();

        $currentMeta = CompletionMeta::where('completion_id', $cid)
            ->whereNull('deleted_on')
            ->orderBy('created_on', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$currentMeta) {
            return response()->json(['error' => 'Completion not found'], 404);
        }

        if ($currentMeta->accepted_by_id !== null) {
            return response()->json(['error' => 'Completion already accepted'], 400);
        }

        $format = $request->input('format');
        $oldFormat = $currentMeta->format_id;

        // Check permissions for both formats (when changing formats)
        $allowedFormats = $user->formatsWithPermission('edit:completion');
        $formats = [$oldFormat, $format];

        if (!in_array(null, $allowedFormats, true) && array_diff($formats, $allowedFormats)) {
            $missing = array_diff($formats, $allowedFormats);
            return response()->json(['error' => "Missing edit:completion permission for format(s): " . implode(', ', $missing)], 403);
        }

        // Check if user is trying to accept their own completion
        $userIds = $request->input('user_ids');
        if (in_array((string) $user->discord_id, $userIds)) {
            return response()->json(['error' => 'Cannot accept your own completion'], 403);
        }

        DB::transaction(function () use ($request, $cid, $userIds, $user) {
            // Handle LCC
            $lccId = null;
            if ($request->has('lcc') && $request->input('lcc') !== null) {
                $lcc = LeastCostChimps::create([
                    'leftover' => $request->input('lcc.leftover'),
                ]);
                $lccId = $lcc->id;
            }

            // Create new metadata version with accepted_by
            $newMeta = CompletionMeta::create([
                'completion_id' => $cid,
                'black_border' => $request->input('black_border'),
                'no_geraldo' => $request->input('no_geraldo'),
                'lcc_id' => $lccId,
                'created_on' => now(),
                'accepted_by_id' => $user->discord_id,
                'format_id' => $request->input('format'),
                'copied_from_id' => null,
            ]);

            // Sync players
            $newMeta->players()->sync($userIds);

            Log::info('Completion accepted', [
                'completion_id' => $cid,
                'accepted_by' => $user->discord_id,
            ]);

            $this->verificationService->createVerificationsForCompletion($newMeta);
        });

        // Dispatch webhook update job (after transaction succeeds)
        $completion = Completion::find($cid);
        if ($completion && $completion->subm_wh_payload !== null) {
            UpdateDiscordWebhookJob::dispatch($cid);
        }

        return response()->json([], 204);
    }

    /**
     * Get unapproved (pending) completions.
     *
     * @OA\Get(
     *     path="/completions/unapproved",
     *     summary="Get unapproved completions",
     *     tags={"Completions"},
     *     @OA\Parameter(
     *         name="formats",
     *         in="query",
     *         description="Comma-separated format IDs",
     *         @OA\Schema(type="string", example="1,51")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="completions", type="array", @OA\Items(ref="#/components/schemas/Completion")),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="pages", type="integer")
     *         )
     *     )
     * )
     */
    public function unapproved(Request $request): JsonResponse
    {
        $formats = $request->input('formats');
        $formatIds = $formats ? explode(',', $formats) : null;

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 50);

        $paginator = Completion::with([
            'proofs',
            'map.latestMeta',
            'latestMeta.format',
            'latestMeta.players',
            'latestMeta.lcc',
        ])
            ->whereHas('latestMeta', function ($query) use ($formatIds) {
                $query->whereNull('deleted_on')
                    ->whereNull('accepted_by_id');

                if ($formatIds) {
                    $query->whereIn('format_id', $formatIds);
                }
            })
            ->orderByDesc('submitted_on')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

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
            $results[] = $this->formatCompletionResponse($completion, $meta, $currentLcc);
        }

        return response()->json([
            'completions' => $results,
            'total' => $paginator->total(),
            'pages' => $paginator->lastPage(),
        ]);
    }

    /**
     * Get recent completions.
     *
     * @OA\Get(
     *     path="/completions/recent",
     *     summary="Get recent completions",
     *     tags={"Completions"},
     *     @OA\Parameter(
     *         name="formats",
     *         in="query",
     *         description="Comma-separated format IDs",
     *         @OA\Schema(type="string", example="1,51")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Completion")
     *         )
     *     )
     * )
     */
    public function recent(Request $request): JsonResponse
    {
        $formats = $request->input('formats');
        $formatIds = $formats ? explode(',', $formats) : [1, 51];

        $completions = Completion::with([
            'proofs',
            'map.latestMeta',
            'latestMeta.format',
            'latestMeta.players',
            'latestMeta.lcc',
        ])
            ->whereHas('latestMeta', function ($query) use ($formatIds) {
                $query->whereNull('deleted_on')
                    ->whereNotNull('accepted_by_id')
                    ->whereIn('format_id', $formatIds);
            })
            ->orderByDesc('submitted_on')
            ->orderByDesc('id')
            ->limit(5)
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
            $results[] = $this->formatCompletionResponse($completion, $meta, $currentLcc);
        }

        return response()->json($results);
    }

    /**
     * Format completion response.
     */
    protected function formatCompletionResponse(Completion $completion, CompletionMeta $meta, bool $currentLcc): array
    {
        return [
            ...$meta->toArray(),
            ...$completion->toArray(),
            'map' => [
                ...$completion->map->latestMeta->toArray(),
                ...$completion->map->toArray(),
            ],
            'users' => $meta->players
                ->map(fn($user) => [
                    'id' => (string) $user->discord_id,
                    'name' => $user->name,
                ])
                ->values(),
            'current_lcc' => $currentLcc,
            'format' => $meta->format_id,
        ];
    }
}
