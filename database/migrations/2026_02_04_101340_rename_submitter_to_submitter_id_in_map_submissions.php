<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('map_submissions', function (Blueprint $table) {
            $table->renameColumn('submitter', 'submitter_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_submissions', function (Blueprint $table) {
            $table->renameColumn('submitter_id', 'submitter');
        });
    }
};
