<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Delete FKs
        Schema::table('retro_maps', function (Blueprint $table) {
            $table->dropForeign('fk_retro_games_1');
        });

        // 2. Delete primary (composite keys)
        Schema::table('retro_games', function (Blueprint $table) {
            $table->dropPrimary();
        });

        // 3. Add primary (id) + unique (old composite)
        Schema::table('retro_games', function (Blueprint $table) {
            $table->primary('id');
            $table->unique(['game_id', 'category_id', 'subcategory_id']);
        });

        // 4. Add FK + drop old columns
        Schema::table('retro_maps', function (Blueprint $table) {
            $table->unsignedBigInteger('retro_game_id')->nullable(false)->change();
            $table->foreign('retro_game_id')->references('id')->on('retro_games')->onDelete('cascade');
            $table->dropColumn('game_id');
            $table->dropColumn('category_id');
            $table->dropColumn('subcategory_id');
        });

        // 5. Update sequence
        DB::statement('CREATE SEQUENCE retro_games_id_seq OWNED BY retro_games.id');
        DB::statement("SELECT setval('retro_games_id_seq', (SELECT MAX(id) FROM retro_games))");
        DB::statement("ALTER TABLE retro_games ALTER COLUMN id SET DEFAULT nextval('retro_games_id_seq')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop FK from retro_maps
        Schema::table('retro_maps', function (Blueprint $table) {
            $table->dropForeign(['retro_game_id']);
        });

        // 2. Drop sequence and default
        DB::statement("ALTER TABLE retro_games ALTER COLUMN id DROP DEFAULT");
        DB::statement('DROP SEQUENCE IF EXISTS retro_games_id_seq');

        // 3. Restore old columns to retro_maps and make retro_game_id nullable
        Schema::table('retro_maps', function (Blueprint $table) {
            $table->unsignedBigInteger('game_id')->after('id');
            $table->unsignedBigInteger('category_id')->after('game_id');
            $table->unsignedBigInteger('subcategory_id')->after('category_id');
            $table->unsignedBigInteger('retro_game_id')->nullable()->change();
        });

        // 3. Backfill composite keys from retro_games
        DB::statement("
            UPDATE retro_maps rm
            SET game_id = rg.game_id,
                category_id = rg.category_id,
                subcategory_id = rg.subcategory_id
            FROM retro_games rg
            WHERE rm.retro_game_id = rg.id
        ");

        // 4. Drop unique and PK (id) from retro_games
        Schema::table('retro_games', function (Blueprint $table) {
            $table->dropUnique(['game_id', 'category_id', 'subcategory_id']);
            $table->dropPrimary();
        });

        // 5. Restore composite PK to retro_games
        Schema::table('retro_games', function (Blueprint $table) {
            $table->primary(['game_id', 'category_id', 'subcategory_id']);
        });

        // 6. Restore FK to retro_games (on composite keys)
        DB::statement("
            ALTER TABLE retro_maps
            ADD CONSTRAINT fk_retro_games_1
            FOREIGN KEY (game_id, category_id, subcategory_id)
            REFERENCES retro_games(game_id, category_id, subcategory_id)
            ON DELETE CASCADE
        ");
    }
};
