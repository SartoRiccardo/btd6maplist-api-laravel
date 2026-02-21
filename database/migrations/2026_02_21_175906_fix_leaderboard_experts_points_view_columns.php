<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::select("
            -- Config vars needed
            CREATE OR REPLACE VIEW leaderboard_experts_points AS
            WITH config_values AS (
                SELECT
                    (SELECT value::float FROM config WHERE name='exp_bb_multi') AS exp_bb_multi,
                    (SELECT value::float FROM config WHERE name='exp_lcc_extra') AS exp_lcc_extra
            ),

            -- Current state of the completions/maps
            active_completion_metas AS (
                SELECT DISTINCT ON (completion_id) *
                FROM completions_meta
                ORDER BY completion_id DESC, created_on DESC
            ),
            active_map_metas AS (
                SELECT DISTINCT ON (code)
                    code, difficulty, deleted_on
                FROM map_list_meta
                ORDER BY code DESC, created_on DESC
            ),

            -- Materialized for Postgres optimization.
            expert_maps AS MATERIALIZED (
                SELECT m.code,
                    c1.value::int AS points,
                    c2.value::int AS extra_nogerry,
                    cv.exp_bb_multi,
                    cv.exp_lcc_extra
                FROM latest_maps_meta(NOW()::timestamp) m
                JOIN config c1
                    ON m.difficulty = c1.difficulty
                    AND c1.name LIKE 'exp_points_%'
                JOIN config c2
                    ON m.difficulty = c2.difficulty
                    AND c2.name LIKE 'exp_nogerry_points_%'
                CROSS JOIN config_values cv
            ),

            -- Run merging and filter applications
            completions_with_flags AS (
                SELECT
                    cm.id AS comp_meta_id,
                    lc.map,
                    cm.no_geraldo,
                    cm.black_border,
                    (lccs.id IS NOT NULL AND lbm.id = lccs.id) AS current_lcc
                FROM completions lc
                JOIN latest_completions cm
                    ON lc.id = cm.completion
                LEFT JOIN leastcostchimps lccs
                    ON lccs.id = cm.lcc
                LEFT JOIN lccs_by_map lbm
                    ON lbm.map = lc.map
                WHERE (
                        cm.format BETWEEN 51 AND 100
                        OR cm.format = 1  -- Explist completions are a superset of Maplist Completions
                    )
                    AND cm.accepted_by IS NOT NULL
                    AND cm.deleted_on IS NULL
            ),
            completion_points AS (
                SELECT
                    c.map,
                    ply.user_id,
                    BOOL_OR(c.no_geraldo) AS no_geraldo,
                    BOOL_OR(c.black_border) AS black_border,
                    BOOL_OR(c.current_lcc) AS current_lcc
                FROM completions_with_flags c
                JOIN comp_players ply
                    ON c.comp_meta_id = ply.run
                GROUP BY (c.map, ply.user_id)
            ),

            -- Final calculations
            leaderboard AS (
                SELECT 
                    cp.user_id, 
                    SUM(
                        m.points * CASE WHEN cp.black_border THEN m.exp_bb_multi ELSE 1 END
                        + CASE WHEN cp.no_geraldo THEN m.extra_nogerry ELSE 0 END
                        + CASE WHEN cp.current_lcc THEN m.exp_lcc_extra ELSE 0 END
                    ) AS score
                FROM completion_points cp
                JOIN expert_maps m
                    ON m.code = cp.map
                GROUP BY (cp.user_id)
            )

            SELECT user_id, score, RANK() OVER(ORDER BY score DESC) AS placement
            FROM leaderboard
            ORDER BY placement ASC, user_id DESC;
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
