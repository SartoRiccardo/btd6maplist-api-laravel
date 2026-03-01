<?php

namespace App\Http\Requests\Completion;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Validator;

/**
 * @OA\Schema(
 *     schema="CompletionRequest",
 *     required={"format_id", "players", "accept"},
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
abstract class CompletionRequest extends BaseRequest
{
    /**
     * Prepare input for validation.
     * Backfill array fields that may be missing in multipart/form-data.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'players' => $this->input('players', []),
            'lcc' => $this->input('lcc', null),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'format_id' => ['required', 'integer', 'min:1', 'exists:formats,id'],
            'black_border' => ['nullable', 'boolean'],
            'no_geraldo' => ['nullable', 'boolean'],
            'players' => ['required', 'array', 'min:1'],
            'players.*' => ['required', 'string', 'regex:/^\d{17,20}$/', 'exists:users,discord_id'],
            'lcc' => ['nullable', 'array'],
            'lcc.leftover' => ['required_with:lcc', 'integer', 'min:0'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();
            $user = auth()->guard('discord')->user();

            if (!$user) {
                $validator->errors()->add('auth', 'Authentication required.');
                return;
            }

            $players = $data['players'] ?? [];

            // Check for duplicate players and add errors for exact indices
            if (is_array($players)) {
                $seen = [];
                foreach ($players as $index => $playerId) {
                    if (isset($seen[$playerId])) {
                        $validator->errors()->add("players.{$index}", 'Duplicate player ID.');
                    } else {
                        $seen[$playerId] = true;
                    }
                }
            }
        });
    }
}
