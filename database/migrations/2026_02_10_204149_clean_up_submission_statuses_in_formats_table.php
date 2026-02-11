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
        Schema::table('formats', function (Blueprint $table) {
            // Drop old integer columns
            $table->dropColumn(['run_submission_status', 'map_submission_status']);
        });

        Schema::table('formats', function (Blueprint $table) {
            // Rename new columns to original names
            $table->renameColumn('run_submission_status_new', 'run_submission_status');
            $table->renameColumn('map_submission_status_new', 'map_submission_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formats', function (Blueprint $table) {
            // Rename back to _new
            $table->renameColumn('run_submission_status', 'run_submission_status_new');
            $table->renameColumn('map_submission_status', 'map_submission_status_new');
        });

        Schema::table('formats', function (Blueprint $table) {
            // Re-add old integer columns
            $table->integer('run_submission_status')->default(0);
            $table->integer('map_submission_status')->default(0);
        });
    }
};
