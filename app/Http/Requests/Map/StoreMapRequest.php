<?php

namespace App\Http\Requests\Map;

/**
 * @OA\Schema(
 *     schema="StoreMapRequest",
 *     required={"code", "name"},
 *     @OA\Property(property="code", type="string", maxLength=10, description="Unique map code", example="TKIEXYSQ")
 * )
 */
class StoreMapRequest extends MapRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'code' => ['required', 'string', 'max:10', 'unique:maps,code'],
        ]);
    }
}
