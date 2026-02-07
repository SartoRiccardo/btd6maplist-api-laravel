<?php

namespace Tests\Helpers;

use App\Models\Map;
use Illuminate\Database\Eloquent\Collection;

class MapTestHelper
{
    /**
     * Merge map and meta data into a single array using Map::jsonStructure().
     *
     * @param Map $map The map model
     * @param mixed $meta The map metadata (can be array or MapListMeta model)
     * @return array The merged structure ready for comparison
     */
    public static function mergeMapMeta(Map $map, mixed $meta): array
    {
        $metaArray = is_array($meta) ? $meta : $meta->toArray();

        return Map::jsonStructure([
            ...$metaArray,
            ...$map->toArray(),
        ]);
    }

    /**
     * Build expected response for paginated map list endpoint.
     *
     * @param Collection $maps Map collection
     * @param Collection $metas MapListMeta collection (must be same count as maps)
     * @param array $metaOverrides Optional pagination meta overrides (current_page, last_page, per_page)
     * @return array Expected response structure with data and meta keys
     */
    public static function expectedMapLists(Collection $maps, Collection $metas, array $metaOverrides = []): array
    {
        $meta = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 100,
            'total' => $maps->count(),
            ...$metaOverrides,
        ];

        return [
            'data' => $maps->zip($metas)
                ->map(fn($pair) => self::mergeMapMeta($pair[0], $pair[1]))
                ->values()
                ->toArray(),
            'meta' => $meta,
        ];
    }
}
