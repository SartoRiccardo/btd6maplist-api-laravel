<?php

namespace Tests\Helpers;

use App\Models\Map;

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
}
