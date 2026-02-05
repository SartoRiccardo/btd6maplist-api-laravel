<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            // Set sequential IDs on retro_games
            DB::statement("
                WITH numbered AS (
                    SELECT game_id, category_id, subcategory_id,
                           ROW_NUMBER() OVER (ORDER BY game_id, category_id, subcategory_id) AS rn
                    FROM retro_games
                )
                UPDATE retro_games rg
                SET id = numbered.rn
                FROM numbered
                WHERE rg.game_id = numbered.game_id
                    AND rg.category_id = numbered.category_id
                    AND rg.subcategory_id = numbered.subcategory_id
            ");

            // Copy IDs to retro_maps matching on composite key
            DB::statement("
                UPDATE retro_maps rm
                SET retro_game_id = rg.id
                FROM retro_games rg
                WHERE rm.game_id = rg.game_id
                    AND rm.category_id = rg.category_id
                    AND rm.subcategory_id = rg.subcategory_id
            ");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No op
    }
};
