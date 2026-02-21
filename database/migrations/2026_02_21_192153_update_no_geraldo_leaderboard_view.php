<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::select("CREATE OR REPLACE FUNCTION leaderboard_no_geraldo(format_id INT)
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
                no_geraldo_completions AS (
                    SELECT DISTINCT c.map_code, lcp.user_id
                    FROM completions c
                    JOIN active_completion_metas r
                        ON c.id = r.completion_id
                    LEFT JOIN formats_rules_subsets f
                        ON r.format_id = f.format_child AND format_id = f.format_parent
                    JOIN comp_players lcp
                        ON r.id = lcp.run
                    WHERE r.no_geraldo
                        AND r.accepted_by_id IS NOT NULL
                        AND r.deleted_on IS NULL
                        AND (
                            f.format_parent IS NOT NULL  -- matched via subset rules
                            OR r.format_id = format_id   -- direct match (fallback when no subset rows)
                        )
                ),
                leaderboard AS (
                    SELECT r.user_id, COUNT(*) AS score
                    FROM no_geraldo_completions r
                    JOIN valid_maps m
                        ON r.map_code = m.code
                    GROUP BY r.user_id
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
