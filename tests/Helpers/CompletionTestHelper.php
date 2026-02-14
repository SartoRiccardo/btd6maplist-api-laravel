<?php

namespace Tests\Helpers;

use App\Models\Completion;
use App\Models\CompletionMeta;
use Illuminate\Database\Eloquent\Collection;

class CompletionTestHelper
{
    /**
     * Merge completion and meta data into a single array using Completion::jsonStructure().
     *
     * @param Completion $completion The completion model
     * @param CompletionMeta $meta The completion metadata (must have completion.map loaded)
     * @param array $overrides Optional field overrides (e.g., ['is_current_lcc' => true])
     * @return array The merged structure ready for comparison
     */
    public static function mergeCompletionMeta(Completion $completion, CompletionMeta $meta, array $overrides = []): array
    {
        $completionArray = $meta->completion->toArray();
        $metaArray = $meta->toArray();

        // Remove the nested 'completion' from meta since we're spreading it at the top level
        unset($metaArray['completion']);

        return Completion::jsonStructure([
            ...$metaArray,
            ...$completionArray,
            'is_current_lcc' => false,
            ...$overrides,
        ]);
    }

    /**
     * Build expected response for paginated completion list endpoint.
     *
     * @param Collection $completions Completion collection
     * @param Collection $metas CompletionMeta collection (must be same count as completions)
     * @param array $metaOverrides Optional pagination meta overrides (current_page, last_page, per_page)
     * @return array Expected response structure with data and meta keys
     */
    public static function expectedCompletionLists(Collection $completions, Collection $metas, array $metaOverrides = []): array
    {
        return self::expectedCompletionListsWithOverrides($completions, $metas, [], $metaOverrides);
    }

    /**
     * Build expected response with field overrides applied to all items.
     *
     * @param Collection $completions Completion collection
     * @param Collection $metas CompletionMeta collection (must be same count as completions)
     * @param array $overrides Field overrides to apply to all items (e.g., ['is_current_lcc' => true])
     * @param array $metaOverrides Optional pagination meta overrides (current_page, last_page, per_page)
     * @return array Expected response structure with data and meta keys
     */
    public static function expectedCompletionListsWithOverrides(Collection $completions, Collection $metas, array $overrides = [], array $metaOverrides = []): array
    {
        $data = $completions->zip($metas)
            ->map(fn($pair) => self::mergeCompletionMeta($pair[0], $pair[1], $overrides))
            ->values()
            ->toArray();

        $meta = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 100,
            'total' => $completions->count(),
            ...$metaOverrides,
        ];

        return [
            'data' => $data,
            'meta' => $meta,
        ];
    }
}
