<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::select("CREATE OR REPLACE FUNCTION leaderboard_lccs(format_id INT)
            RETURNS TABLE (
                user_id BIGINT,
                score INT,
                placement INT
            )
            IMMUTABLE
            LANGUAGE sql
            AS $$
                -- Current state of the completions/maps
                WITH active_completion_metas AS (
                    SELECT DISTINCT ON (completion_id) *
                    FROM completions_meta
                    ORDER BY completion_id DESC, created_on DESC
                ),
                active_map_metas AS (
                    SELECT DISTINCT ON (code)
                        code, difficulty, deleted_on, placement_curver, placement_allver, remake_of, botb_difficulty
                    FROM map_list_meta
                    ORDER BY code DESC, created_on DESC
                ),

                -- Materialized for Postgres optimization
                valid_maps AS MATERIALIZED (
                    SELECT *
                    FROM active_map_metas m
                    WHERE (
                            format_id = 1 AND m.placement_curver BETWEEN 1 AND (SELECT value::int FROM config WHERE name='map_count')
                            OR format_id = 2 AND m.placement_allver BETWEEN 1 AND (SELECT value::int FROM config WHERE name='map_count')
                            OR format_id = 51 AND m.difficulty >= 0
                            OR format_id = 11 AND m.remake_of IS NOT NULL
                            OR format_id = 52 AND m.botb_difficulty >= 0
                        )
                        AND m.deleted_on IS NULL
                ),

                -- Completion building & calculations
                valid_lccs AS (
                    SELECT DISTINCT ON (map) *
                    FROM lccs_by_map lccs
                    LEFT JOIN formats_rules_subsets f
                        ON lccs.format = f.format_child AND format_id = f.format_parent
                    WHERE f.format_parent IS NOT NULL  -- matched via subset rules
                        OR lccs.format = format_id   -- direct match (fallback when no subset rows)
                    ORDER BY lccs.map DESC, lccs.leftover DESC
                ),
                leaderboard AS (
                    SELECT lcp.user_id, COUNT(lcp.user_id) AS score
                    FROM valid_lccs lccs
                    JOIN active_completion_metas r
                        ON r.lcc_id = lccs.id
                    JOIN valid_maps m
                        ON lccs.map = m.code
                    JOIN comp_players lcp
                        ON r.id = lcp.run
                    WHERE r.accepted_by_id IS NOT NULL
                        AND r.deleted_on IS NULL
                    GROUP BY lcp.user_id
                )
                SELECT user_id, score, RANK() OVER(ORDER BY score DESC) AS placement
                FROM leaderboard
                ORDER BY placement ASC, user_id DESC
            $$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
