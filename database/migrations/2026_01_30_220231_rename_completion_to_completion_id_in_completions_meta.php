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
            $table->dropForeign('fk_completions_1');
            $table->renameColumn('completion', 'completion_id');
            $table->foreign('completion_id')->references('id')->on('completions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('completions_meta', function (Blueprint $table) {
            $table->dropForeign(['completion_id']);
            $table->renameColumn('completion_id', 'completion');
            $table->foreign('completion')->references('id')->on('completions')->onDelete('cascade');
        });
    }
};
