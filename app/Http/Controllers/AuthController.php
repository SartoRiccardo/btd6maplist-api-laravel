<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    /**
     * Creates a user based on a Discord profile if it's not in the database, and
     * returns its Maplist profile.
     *
     * @OA\Post(
     *     path="/auth",
     *     summary="Get user profile",
     *     description="Validates Discord Bearer token, creates user if needed, returns full profile",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Returns the user's Maplist profile",
     *         @OA\JsonContent(ref="#/components/schemas/UserProfile")
     *     ),
     *     @OA\Response(response=401, description="discord_token is missing or invalid")
     * )
     */
    public function authenticate(Request $request): JsonResponse
    {
        $user = auth()->guard('discord')->user();

        return response()->json($user->toArray());
    }

    /**
     * Marks that the user has seen the rules popup.
     *
     * @OA\Put(
     *     path="/read-rules",
     *     summary="Mark rules as read",
     *     description="Sets has_seen_popup to true for the authenticated user. Idempotent operation.",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=204, description="Rules marked as read successfully"),
     *     @OA\Response(response=401, description="discord_token is missing or invalid")
     * )
     */
    public function readRules(Request $request): Response
    {
        $user = auth()->guard('discord')->user();

        $user->has_seen_popup = true;
        $user->save();

        return response()->noContent();
    }
}
