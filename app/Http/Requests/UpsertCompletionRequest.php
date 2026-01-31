<?php

namespace App\Http\Requests;

/**
 * @OA\Schema(
 *     schema="UpsertCompletionRequest",
 *     type="object",
 *     required={"user_ids", "format", "black_border", "no_geraldo"},
 *     @OA\Property(property="user_ids", type="array", @OA\Items(type="string"), description="Array of user Discord IDs"),
 *     @OA\Property(property="format", type="integer", description="Format ID"),
 *     @OA\Property(property="black_border", type="boolean"),
 *     @OA\Property(property="no_geraldo", type="boolean"),
 *     @OA\Property(property="lcc", type="object", nullable=true,
 *         @OA\Property(property="leftover", type="integer", description="Cash leftover for LCC"),
 *     )
 * )
 */
class UpsertCompletionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|string|exists:users,discord_id',
            'format' => 'required|integer|exists:formats,id',
            'black_border' => 'required|boolean',
            'no_geraldo' => 'required|boolean',
            'lcc' => 'nullable|array',
            'lcc.leftover' => 'required_with:lcc|integer|min:0',
        ];
    }
}
