<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('map_aliases', function ($table) {
            $table->dropForeign('fk_maps_1');
        });
        Schema::table('map_aliases', function ($table) {
            $table->renameColumn('map', 'map_code');
        });
        Schema::table('map_aliases', function ($table) {
            $table->foreign('map_code')->references('code')->on('maps')->onDelete('cascade')->name('fk_maps_1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_aliases', function ($table) {
            $table->dropForeign('fk_maps_1');
        });
        Schema::table('map_aliases', function ($table) {
            $table->renameColumn('map_code', 'map');
        });
        Schema::table('map_aliases', function ($table) {
            $table->foreign('map')->references('code')->on('maps')->onDelete('cascade')->name('fk_maps_1');
        });
    }
};
