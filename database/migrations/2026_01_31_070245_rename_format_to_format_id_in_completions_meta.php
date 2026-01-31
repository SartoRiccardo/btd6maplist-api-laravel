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
        Schema::table('completions_meta', function (Blueprint $table) {
            $table->renameColumn('format', 'format_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('completions_meta', function (Blueprint $table) {
            $table->renameColumn('format_id', 'format');
        });
    }
};
