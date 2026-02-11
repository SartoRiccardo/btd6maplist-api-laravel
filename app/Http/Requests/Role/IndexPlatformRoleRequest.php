<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\BaseRequest;

/**
 * @OA\Parameter(
 *     name="page",
 *     in="query",
 *     description="Page number",
 *     @OA\Schema(type="integer", minimum=1)
 * )
 *
 * @OA\Parameter(
 *     name="per_page",
 *     in="query",
 *     description="Items per page",
 *     @OA\Schema(type="integer", minimum=1, maximum=100)
 * )
 */
class IndexPlatformRoleRequest extends BaseRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 100),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
