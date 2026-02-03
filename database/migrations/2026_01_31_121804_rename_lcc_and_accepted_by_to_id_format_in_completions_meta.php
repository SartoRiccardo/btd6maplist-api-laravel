<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('completions_meta', function (Blueprint $table) {
            $table->dropForeign('fk_lccs_1');
            $table->renameColumn('lcc', 'lcc_id');
            $table->renameColumn('accepted_by', 'accepted_by_id');
            $table->foreign('lcc_id')->references('id')->on('leastcostchimps')->onDelete('SET NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('completions_meta', function (Blueprint $table) {
            $table->dropForeign(['lcc_id']);
            $table->renameColumn('lcc_id', 'lcc');
            $table->renameColumn('accepted_by_id', 'accepted_by');
            $table->foreign('lcc', 'fk_lccs_1')->references('id')->on('leastcostchimps')->onDelete('SET NULL');
        });
    }
};
