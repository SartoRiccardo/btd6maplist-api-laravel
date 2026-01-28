<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFormatRequest;
use App\Models\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FormatController extends Controller
{
    /**
     * Returns a list of all available formats. No authentication required.
     *
     * @OA\Get(
     *     path="/formats",
     *     summary="Get all formats",
     *     description="Returns a list of all available formats. No authentication required.",
     *     tags={"Formats"},
     *     @OA\Response(
     *         response=200,
     *         description="Array of formats",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Format")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $formats = Format::orderBy('id')->get();

        return response()->json($formats);
    }

    /**
     * Get in-depth information about a format. Requires edit:config permission.
     * Returns sensitive information including webhook URLs.
     *
     * @OA\Get(
     *     path="/formats/{id}",
     *     summary="Get specific format details",
     *     description="Get in-depth information about a format including webhooks. Requires edit:config permission for the format.",
     *     tags={"Formats"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The format's ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Full format information",
     *         @OA\JsonContent(ref="#/components/schemas/FullFormat")
     *     ),
     *     @OA\Response(response=401, description="No token found or invalid token"),
     *     @OA\Response(response=403, description="Missing edit:config permission for this format"),
     *     @OA\Response(response=404, description="Format not found")
     * )
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $format = Format::findOrFail($id);
        $user = auth()->guard('discord')->user();

        if (!$user->hasPermission('edit:config', $format->id)) {
            return response()->json(['error' => 'Missing edit:config permission for this format'], 403);
        }

        return response()->json($format->toFullArray());
    }

    /**
     * Edit a format. Requires edit:config permission.
     *
     * @OA\Put(
     *     path="/formats/{id}",
     *     summary="Edit format",
     *     description="Edit format properties. Requires edit:config permission for the format.",
     *     tags={"Formats"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The format's ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateFormatRequest")
     *     ),
     *     @OA\Response(response=204, description="Format updated successfully"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No token found or invalid token"),
     *     @OA\Response(response=403, description="Missing edit:config permission for this format"),
     *     @OA\Response(response=404, description="Format not found")
     * )
     */
    public function update(int $id, UpdateFormatRequest $request): JsonResponse|Response
    {
        $format = Format::findOrFail($id);
        $user = auth()->guard('discord')->user();

        if (!$user->hasPermission('edit:config', $format->id)) {
            return response()->json(['error' => 'Missing edit:config permission for this format'], 403);
        }

        $format->update($request->validated());

        return response()->noContent();
    }
}
