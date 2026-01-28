<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseRequest;

class UpdateFormatRequest extends BaseRequest
{
    /**
     * @OA\Schema(
     *     schema="UpdateFormatRequest",
     *     type="object",
     *     required={"hidden", "run_submission_status", "map_submission_status"},
     *     @OA\Property(property="hidden", type="boolean"),
     *     @OA\Property(property="run_submission_status", type="string", enum={"closed", "open", "lcc_only"}),
     *     @OA\Property(property="map_submission_status", type="string", enum={"closed", "open", "open_chimps"}),
     *     @OA\Property(property="map_submission_wh", type="string", nullable=true, format="uri"),
     *     @OA\Property(property="run_submission_wh", type="string", nullable=true, format="uri"),
     *     @OA\Property(property="emoji", type="string", nullable=true)
     * )
     */
    public function rules(): array
    {
        return [
            'hidden' => 'required|boolean',
            'run_submission_status' => 'required|in:closed,open,lcc_only',
            'map_submission_status' => 'required|in:closed,open,open_chimps',
            'map_submission_wh' => 'nullable|url',
            'run_submission_wh' => 'nullable|url',
            'emoji' => 'nullable|string|max:255',
        ];
    }
}
