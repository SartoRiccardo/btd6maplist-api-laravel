<?php

namespace App\Http\Requests\Map;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     @OA\Property(property="name", type="string", maxLength=255, description="Map name", example="In The Loop"),
 *     @OA\Property(property="r6_start", type="integer", nullable=true, minimum=0, description="BTD6 version when map was added", example=10),
 *     @OA\Property(property="map_data", type="string", nullable=true, description="Map data JSON"),
 *     @OA\Property(property="map_preview_url", type="string", format="uri", maxLength=500, nullable=true, description="URL to map preview image"),
 *     @OA\Property(property="map_notes", type="string", maxLength=1000, nullable=true, description="Additional notes about the map"),
 *     @OA\Property(property="placement_curver", type="integer", nullable=true, minimum=1, description="Current version placement (requires MAPLIST edit:map permission)"),
 *     @OA\Property(property="placement_allver", type="integer", nullable=true, minimum=1, description="All-time version placement (requires MAPLIST_ALL_VERSIONS edit:map permission)"),
 *     @OA\Property(property="difficulty", type="integer", nullable=true, minimum=1, description="Difficulty level (requires EXPERT_LIST edit:map permission)"),
 *     @OA\Property(property="optimal_heros", type="array", nullable=true, @OA\Items(type="string"), description="Optimal heroes for this map"),
 *     @OA\Property(property="botb_difficulty", type="integer", nullable=true, minimum=1, description="Brown Border Bloat difficulty (requires BEST_OF_THE_BEST edit:map permission)"),
 *     @OA\Property(property="remake_of", type="integer", nullable=true, description="ID of the retro map this is a remake of (requires NOSTALGIA_PACK edit:map permission)"),
 *     @OA\Property(property="creators", type="array", nullable=true, @OA\Items(
 *         @OA\Property(property="user_id", type="string", description="Discord user ID"),
 *         @OA\Property(property="role", type="string", nullable=true, description="Creator role", example="Gameplay")
 *     ), description="Array of creators with user IDs and optional roles"),
 *     @OA\Property(property="verifiers", type="array", nullable=true, @OA\Items(
 *         @OA\Property(property="user_id", type="string", description="Discord user ID"),
 *         @OA\Property(property="version", type="integer", nullable=true, description="BTD6 version (null = versionless)")
 *     ), description="Array of verifiers with user IDs and optional versions")
 * )
 */
class MapRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Map fields
            'name' => ['required', 'string', 'max:255'],
            'r6_start' => ['nullable', 'integer', 'min:0'],
            'map_data' => ['nullable', 'string'],
            'map_preview_url' => ['nullable', 'url', 'max:500'],
            'map_notes' => ['nullable', 'string', 'max:1000'],

            // MapListMeta fields (all nullable, permission-checked in controller)
            'placement_curver' => ['nullable', 'integer', 'min:1'],
            'placement_allver' => ['nullable', 'integer', 'min:1'],
            'difficulty' => ['nullable', 'integer', 'between:0,4'],
            'optimal_heros' => ['nullable', 'array'],
            'optimal_heros.*' => ['string'],
            'botb_difficulty' => ['nullable', 'integer', 'between:0,4'],
            'remake_of' => ['nullable', 'integer', 'exists:retro_maps,id'],

            // Relations
            'creators' => ['nullable', 'array'],
            'creators.*.user_id' => ['required', 'string', 'exists:users,discord_id'],
            'creators.*.role' => ['nullable', 'string'],
            'verifiers' => ['nullable', 'array'],
            'verifiers.*.user_id' => ['required', 'string', 'exists:users,discord_id'],
            'verifiers.*.version' => ['nullable', 'integer'],
        ];
    }
}
