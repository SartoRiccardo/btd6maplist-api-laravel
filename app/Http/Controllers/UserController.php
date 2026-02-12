<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UserController
{
    /**
     * @OA\Get(
     *     path="/users/{id}",
     *     summary="Get user by ID",
     *     description="Returns a user's profile data including their platform roles. If the user has a Ninja Kiwi OAK set and 'flair' is in the include parameter, avatar and banner URLs are fetched from the Ninja Kiwi API. If 'medals' is included, medal statistics are calculated for the specified timestamp.",
     *     tags={"Users"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The user's Discord ID",
     *         @OA\Schema(type="string", example="123456789012345678")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         required=false,
     *         description="Comma-separated list of additional data to include. Use 'flair' to include avatar_url and banner_url, 'medals' to include medal statistics.",
     *         @OA\Schema(type="string", example="flair,medals")
     *     ),
     *     @OA\Parameter(
     *         name="timestamp",
     *         in="query",
     *         required=false,
     *         description="Unix timestamp to calculate medals at. Defaults to current time.",
     *         @OA\Schema(type="integer", example=1704067200)
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="User profile data",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response="404", description="User not found")
     * )
     */
    public function show(string $id, Request $request)
    {
        $includes = array_filter(explode(',', $request->query('include', '')));
        $includeFlair = in_array('flair', $includes, true);
        $includeMedals = in_array('medals', $includes, true);

        // Default timestamp to now, similar to CompletionController
        $timestamp = $request->query('timestamp', Carbon::now()->unix());
        if (!is_numeric($timestamp)) {
            return response()->json(['message' => 'Invalid timestamp'], 422);
        }
        $timestamp = (int) $timestamp;

        $user = User::with('roles')->find($id);
        if (!$user) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        $response = $user->toArray();

        // Only fetch and include NK data if 'flair' is in includes
        if ($includeFlair) {
            $deco = null;
            if ($user->nk_oak) {
                $deco = NinjaKiwiApiClient::getBtd6UserDeco($user->nk_oak);
            }

            $response['avatar_url'] = $deco['avatar_url'] ?? null;
            $response['banner_url'] = $deco['banner_url'] ?? null;
        }

        // Include medal statistics if 'medals' is in includes
        if ($includeMedals) {
            $response['medals'] = $user->medals($timestamp);
        }

        return response()->json($response);
    }

    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function banUser(Request $request, $id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }

    public function unbanUser(Request $request, $id)
    {
        return response()->json(['message' => 'Not Implemented'], 501);
    }
}
