<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Illuminate\Http\Request;

class UserController
{
    /**
     * @OA\Get(
     *     path="/users/{id}",
     *     summary="Get user by ID",
     *     description="Returns a user's profile data including their platform roles. If the user has a Ninja Kiwi OAK set and 'flair' is in the include parameter, avatar and banner URLs are fetched from the Ninja Kiwi API.",
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
     *         description="Comma-separated list of additional data to include. Use 'flair' to include avatar_url and banner_url.",
     *         @OA\Schema(type="string", example="flair", pattern="^flair(?:,flair)*$")
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
