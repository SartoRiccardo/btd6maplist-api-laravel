<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAchievementRolesRequest;
use App\Models\AchievementRole;
use App\Models\DiscordRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AchievementRoleController extends Controller
{
    /**
     * Returns all achievement roles with linked Discord roles.
     * No authentication required.
     *
     * @OA\Get(
     *     path="/roles/achievement",
     *     summary="Get all achievement roles",
     *     description="Returns all achievement roles with linked Discord roles. No authentication required.",
     *     tags={"Achievement Roles"},
     *     @OA\Response(
     *         response=200,
     *         description="Array of achievement roles",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/AchievementRole")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $roles = AchievementRole::orderBy('lb_format')
            ->orderBy('lb_type')
            ->orderBy('threshold')
            ->get();

        // Load all discord roles and group them by their composite key
        $discordRoles = DiscordRole::all()
            ->groupBy(function ($item) {
                return $item['ar_lb_format'] . '-' . $item['ar_lb_type'] . '-' . $item['ar_threshold'];
            });

        // Transform to array with linked_roles
        $response = $roles->map(fn($role) => [
            ...$role->toArray(),
            'linked_roles' => $discordRoles
                ->get($role->lb_format . '-' . $role->lb_type . '-' . $role->threshold, collect())
                ->toArray(),
        ])
            ->toArray();

        return response()->json($response);
    }

    /**
     * Modify Achievement Roles for a specific leaderboard.
     * Requires edit:achievement_roles permission.
     *
     * @OA\Put(
     *     path="/roles/achievement",
     *     summary="Update achievement roles",
     *     description="Modify Achievement Roles for a specific leaderboard. Requires edit:achievement_roles permission.",
     *     tags={"Achievement Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateAchievementRolesRequest")
     *     ),
     *     @OA\Response(response=204, description="Roles updated successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - missing permission"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateAchievementRolesRequest $request): JsonResponse|Response
    {
        $user = auth()->guard('discord')->user();

        if (!$user->hasPermission('edit:achievement_roles', $request->lb_format)) {
            return response()->json([
                'error' => "You are missing edit:achievement_roles on {$request->lb_format}"
            ], 403);
        }

        DB::transaction(function () use ($request) {
            // Delete existing achievement_roles for this (lb_format, lb_type)
            // Cascade delete handles discord_roles automatically
            AchievementRole::where('lb_format', $request->lb_format)
                ->where('lb_type', $request->lb_type)
                ->delete();

            // Insert new achievement_roles
            foreach ($request->roles as $roleData) {
                AchievementRole::create([
                    ...$request->toArray(),
                    ...$roleData,
                ]);

                foreach ($roleData['linked_roles'] as $dr) {
                    DiscordRole::create([
                        'ar_lb_format' => $request->lb_format,
                        'ar_lb_type' => $request->lb_type,
                        'ar_threshold' => $roleData['threshold'],
                        ...$dr,
                    ]);
                }
            }
        });

        return response()->noContent();
    }
}
