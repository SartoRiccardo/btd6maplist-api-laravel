<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW leaderboard_maplist_points AS

            -- Config vars needed
            WITH config_values AS (
                SELECT
                    (SELECT value::float FROM config WHERE name='points_multi_bb') AS points_multi_bb,
                    (SELECT value::float FROM config WHERE name='points_multi_gerry') AS points_multi_gerry,
                    (SELECT value::float FROM config WHERE name='points_extra_lcc') AS points_extra_lcc
            ),

            -- Current state of the completions/maps
            active_completion_metas AS (
                SELECT DISTINCT ON (completion_id) *
                FROM completions_meta
                ORDER BY completion_id DESC, created_on DESC
            ),
            active_map_metas AS (
                SELECT DISTINCT ON (code)
                    code, placement_curver, created_on, deleted_on
                FROM map_list_meta
                ORDER BY code DESC, created_on DESC
            ),
            -- Materialized for Postgres optimization.
            maps_points AS MATERIALIZED (
                SELECT
                    lmp.points, m.code
                FROM active_map_metas m
                JOIN listmap_points lmp
                    -- Implicitly puts placement BETWEEN 1 AND map_count
                    ON lmp.placement = m.placement_curver
                WHERE m.deleted_on IS NULL
            ),

            -- Run merging and filter applications
            unique_runs AS (
                SELECT DISTINCT
                    lcp.user_id, c.map_code, cm.black_border, cm.no_geraldo, cm.lcc_id=lccs.id AS current_lcc
                FROM completions c
                JOIN active_completion_metas cm
                    ON c.id = cm.completion_id
                JOIN comp_players lcp
                    ON cm.id = lcp.run
                LEFT JOIN lccs_by_map lccs
                    ON lccs.map = c.map_code AND lccs.format = cm.format_id
                WHERE cm.format_id = 1
                    AND cm.accepted_by_id IS NOT NULL
                    AND cm.deleted_on IS NULL
            ),
            -- https://stackoverflow.com/a/78963508/13033269
            comp_user_map_modifiers AS (
                SELECT uq.user_id, uq.map_code,
                    CASE WHEN bool_or(uq.black_border AND uq.no_geraldo) THEN cv.points_multi_bb*cv.points_multi_gerry
                        ELSE GREATEST(CASE WHEN bool_or(uq.black_border) THEN cv.points_multi_bb ELSE 0 END
                                        + CASE WHEN bool_or(uq.no_geraldo) THEN cv.points_multi_gerry ELSE 0 END, 1)
                        END AS multiplier,
                    CASE WHEN bool_or(uq.current_lcc) THEN cv.points_extra_lcc ELSE 0 END AS additive
            FROM unique_runs uq
            CROSS JOIN config_values cv
            GROUP BY uq.user_id, uq.map_code, cv.points_multi_bb, cv.points_multi_gerry, cv.points_extra_lcc
            ),

            -- Final calculations
            user_points AS (
                SELECT
                    modi.user_id,
                    mwp.points * modi.multiplier + modi.additive AS points
                FROM comp_user_map_modifiers modi
                JOIN maps_points mwp
                    ON modi.map_code = mwp.code
            ),
            leaderboard AS (
                SELECT
                    up.user_id,
                    SUM(up.points) AS score
                FROM user_points up
                GROUP BY up.user_id
            )
            SELECT user_id, score, RANK() OVER (ORDER BY score DESC) AS placement
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
