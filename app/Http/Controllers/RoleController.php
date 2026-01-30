<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    /**
     * Returns a list of all available roles, excluding internal roles.
     * No authentication required.
     *
     * @OA\Get(
     *     path="/roles",
     *     summary="Get all roles",
     *     description="Returns a list of all available roles, excluding internal roles. No authentication required.",
     *     tags={"Roles"},
     *     @OA\Response(
     *         response=200,
     *         description="Array of roles with id, name, and can_grant array",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Role")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $roles = Role::where('internal', false)
            ->with('canGrant:id')
            ->get();

        return response()->json($roles);
    }
}
