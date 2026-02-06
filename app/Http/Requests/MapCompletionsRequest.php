<?php

namespace App\Http\Requests;

/**
 * @OA\Schema(
 *     schema="MapCompletionsRequest",
 *     type="object",
 *     @OA\Property(property="page", type="integer", description="Page number", example=1, minimum=1),
 *     @OA\Property(property="formats", type="string", description="Comma-separated format IDs", example="1,51")
 * )
 */
class MapCompletionsRequest extends BaseRequest
{
    public array $parsedFormats = [];

    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'formats' => 'nullable|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Parse formats string into array of integers
        if ($this->has('formats')) {
            $this->parsedFormats = array_map(
                'intval',
                array_filter(explode(',', $this->input('formats')), 'is_numeric')
            );
        }
    }

    public function getParsedFormats(): array
    {
        return $this->parsedFormats ?: [1, 51];
    }
}
