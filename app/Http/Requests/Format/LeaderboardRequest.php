<?php

namespace App\Http\Requests\Format;

use App\Constants\FormatConstants;
use App\Http\Requests\BaseRequest;

/**
 * @OA\Schema(
 *     schema="LeaderboardRequest",
 *     @OA\Property(
 *         property="value",
 *         type="string",
 *         description="Leaderboard type",
 *         enum={"points", "lccs", "no_geraldo", "black_border"},
 *         example="points"
 *     ),
 *     @OA\Property(property="page", type="integer", description="Page number", example=1, minimum=1),
 *     @OA\Property(property="per_page", type="integer", description="Items per page", example=50, minimum=1, maximum=100),
 *     @OA\Property(property="include", type="string", description="Comma-separated list of additional data to include (e.g., 'user.flair')", example="user.flair")
 * )
 */
class LeaderboardRequest extends BaseRequest
{

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 50),
            'value' => $this->input('value', 'points'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'value' => ['nullable', 'in:' . implode(',', FormatConstants::LEADERBOARD_TYPES)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'include' => ['nullable', 'string'],
        ];
    }

    /**
     * Get parsed includes.
     */
    public function includes(): array
    {
        return array_filter(explode(',', $this->validated()['include'] ?? ''));
    }

    /**
     * Get the leaderboard table/view name for the given format and value type.
     */
    public function getLeaderboardName(int $formatId): ?string
    {
        $value = $this->validated()['value'] ?? 'points';

        if ($value === 'points') {
            return FormatConstants::LEADERBOARD_NAMES[$formatId] ?? null;
        }

        return FormatConstants::LEADERBOARD_FUNCTIONS[$value] ?? null;
    }

    /**
     * Build the leaderboard query for the given format.
     */
    public function buildLeaderboardQuery(int $formatId): string
    {
        $value = $this->validated()['value'] ?? 'points';
        $leaderboardName = $this->getLeaderboardName($formatId);

        if (!$leaderboardName) {
            throw new \InvalidArgumentException("Invalid format or value combination");
        }

        if ($value === 'points') {
            return "SELECT * FROM leaderboard_{$leaderboardName}_points";
        }

        return "SELECT * FROM {$leaderboardName}({$formatId})";
    }
}
