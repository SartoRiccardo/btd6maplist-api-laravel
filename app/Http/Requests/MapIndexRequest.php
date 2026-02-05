<?php

namespace App\Http\Requests;

/**
 * @OA\Schema(
 *     schema="MapIndexRequest",
 *     type="object",
 *     @OA\Property(property="format", type="integer", description="Format ID (1, 2, 11, 51, 52)", example=1),
 *     @OA\Property(property="filter", type="integer", description="Filter value required for some formats (e.g., difficulty for format 51)", example=1)
 * )
 */
class MapIndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'format' => 'nullable|integer',
            'filter' => 'nullable|integer',
        ];
    }
}
