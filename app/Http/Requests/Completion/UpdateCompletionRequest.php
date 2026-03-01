<?php

namespace App\Http\Requests\Completion;

/**
 * @OA\Schema(
 *     schema="UpdateCompletionRequest",
 *     @OA\Property(property="format_id", type="integer", description="Format ID", example=1, minimum=1),
 *     @OA\Property(property="black_border", type="boolean", nullable=true, description="Whether the completion achieved black border"),
 *     @OA\Property(property="no_geraldo", type="boolean", nullable=true, description="Whether the completion was done without Geraldo"),
 *     @OA\Property(property="players", type="array", minItems=1, description="List of player Discord IDs who participated in the completion",
 *         @OA\Items(type="string", pattern="^\d{17,20}$", example="123456789012345678")
 *     ),
 *     @OA\Property(property="lcc", type="object", nullable=true, description="Lowest Cost Chimps data",
 *         @OA\Property(property="leftover", type="integer", description="Cash leftover at end of run", minimum=0, example=5000)
 *     ),
 *     @OA\Property(property="accept", type="boolean", description="Whether to accept the completion. Requires edit:completion permission on the format.")
 * )
 */
class UpdateCompletionRequest extends CompletionRequest
{
    /**
     * Get the validation rules that apply to the request.
     * Uses the base CompletionRequest rules plus accept.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'accept' => ['required', 'boolean'],
        ]);
    }
}
