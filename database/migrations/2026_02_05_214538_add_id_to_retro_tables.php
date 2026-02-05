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
        // Add id column to retro_games (will become PK in next migration)
        Schema::table('retro_games', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable()->first();
        });

        // Add retro_game_id column to retro_maps (will be FK in next migration)
        Schema::table('retro_maps', function (Blueprint $table) {
            $table->unsignedBigInteger('retro_game_id')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('retro_maps', function (Blueprint $table) {
            $table->dropColumn('retro_game_id');
        });

        Schema::table('retro_games', function (Blueprint $table) {
            $table->dropColumn('id');
        });
    }
};
