<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateConfigRequest;
use App\Models\Config;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    /**
     * Get all config variables.
     *
     * @OA\Get(
     *     path="/config",
     *     summary="Get all config variables",
     *     description="Returns all project config variables with their values, types, and associated formats.",
     *     tags={"Config"},
     *     @OA\Response(
     *         response=200,
     *         description="Config variables keyed by name",
     *         @OA\JsonContent(
     *             type="object",
     *             additionalProperties=@OA\Schema(ref="#/components/schemas/Config")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $configs = Config::with('configFormats.format')
            ->get()
            ->keyBy('name')
            ->map(fn($config) => $config->toArray());

        return response()->json($configs);
    }

    /**
     * Update config variables.
     *
     * @OA\Put(
     *     path="/config",
     *     summary="Update config variables",
     *     description="Updates multiple config variables. Only updates variables for formats where the user has edit:config permission. Changes to variables without permission are silently ignored.",
     *     tags={"Config"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateConfigRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Config variables updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="errors", type="object", example={}),
     *             @OA\Property(property="data", type="object", example={"points_top_map": 150})
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="errors", type="object", example={"config.invalid_key": "Invalid key"}),
     *             @OA\Property(property="data", type="array", example={})
     *         )
     *     ),
     *     @OA\Response(response=401, description="No token found or invalid token"),
     *     @OA\Response(response=403, description="Missing edit:config permission")
     * )
     */
    public function update(UpdateConfigRequest $request): JsonResponse
    {
        $user = auth()->guard('discord')->user();
        $requestedConfigs = $request->input('config');

        $permittedFormatIds = $user->formatsWithPermission('edit:config');
        if (!count($permittedFormatIds)) {
            return response()->json(['error' => 'Missing edit:config permission'], 403);
        }

        // Get all existing configs that user has permission for
        $configs = Config::with('configFormats')
            ->whereIn('name', array_keys($requestedConfigs))
            ->whereHas('configFormats', fn($q) => $q->whereIn('format_id', $permittedFormatIds))
            ->get()
            ->keyBy('name');

        // Check each config for permission
        $errors = [];
        $data = [];
        $toUpdate = [];

        foreach ($requestedConfigs as $key => $newValue) {
            if (!isset($configs[$key])) {
                $errors[$key] = 'Invalid key';
                continue;
            }
            $toUpdate[$key] = ['value' => $newValue];
            $data[$key] = $configs[$key]->castValue($newValue, $configs[$key]->type);
        }

        if (!empty($toUpdate)) {
            \DB::transaction(function () use ($toUpdate) {
                foreach ($toUpdate as $key => $updateData) {
                    Config::where('name', $key)->update($updateData);
                }
            });

            \Log::info('Config updated', [
                'user_id' => $user->discord_id,
                'changes' => $data,
            ]);
        }

        return response()->json([
            'errors' => $errors,
            'data' => $data,
        ]);
    }
}
