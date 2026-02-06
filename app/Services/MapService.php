<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MapService
{
    /**
     * Get a map code by various identifiers (code, name, alias, placement).
     *
     * @param string $identifier The search term (code, name, alias, or placement)
     * @return string|null The map code, or null if not found
     */
    public static function getPossibleMap(string $identifier): ?string
    {
        $searchCode = str_replace(' ', '_', $identifier);
        $timestamp = now();

        $result = DB::selectOne("
            WITH possible_map AS (
                SELECT * FROM (
                    SELECT 1 AS ord, m.code
                    FROM maps m
                    JOIN latest_maps_meta(?) mlm ON m.code = mlm.code
                    WHERE m.code = ? AND mlm.deleted_on IS NULL

                    UNION

                    SELECT 2 AS ord, m.code
                    FROM maps m
                    JOIN latest_maps_meta(?) mlm ON m.code = mlm.code
                    WHERE LOWER(m.name) = LOWER(?) AND mlm.deleted_on IS NULL

                    UNION

                    SELECT 4 AS ord, m.code
                    FROM maps m
                    JOIN map_list_meta mlm ON m.code = mlm.code
                    JOIN map_aliases a ON m.code = a.map_code
                    WHERE LOWER(a.alias) = LOWER(?) OR LOWER(a.alias) = LOWER(?)

                    UNION

                    (
                        SELECT 6 AS ord, m.code
                        FROM maps m
                        JOIN map_list_meta mlm ON m.code = mlm.code
                        WHERE m.code = ? AND mlm.deleted_on IS NOT NULL
                        ORDER BY mlm.created_on DESC
                        LIMIT 1
                    )

                    UNION

                    (
                        SELECT 7 AS ord, m.code
                        FROM maps m
                        JOIN map_list_meta mlm ON m.code = mlm.code
                        WHERE LOWER(m.name) = LOWER(?) AND mlm.deleted_on IS NOT NULL
                        ORDER BY mlm.created_on DESC
                        LIMIT 1
                    )
                ) possible
                ORDER BY ord
                LIMIT 1
            )
            SELECT code FROM possible_map
        ", [
            $timestamp,
            $searchCode,
            $timestamp,
            $identifier,
            $searchCode,
            $identifier,
            $searchCode,
            $identifier,
        ]);

        return $result?->code;
    }
}
