<?php

namespace App\Http\Requests;

/**
 * @OA\Schema(
 *     schema="MapSubmissionsRequest",
 *     type="object",
 *     @OA\Property(property="page", type="integer", description="Page number", example=1, minimum=1),
 *     @OA\Property(property="pending", type="string", enum={"pending", "all"}, description="Filter pending or all submissions", example="pending")
 * )
 */
class MapSubmissionsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'pending' => 'nullable|in:pending,all',
        ];
    }

    public function getPage(): int
    {
        return max(1, (int) $this->input('page', 1));
    }

    public function getPending(): string
    {
        $pending = $this->input('pending', 'pending');
        return in_array($pending, ['pending', 'all']) ? $pending : 'pending';
    }
}
