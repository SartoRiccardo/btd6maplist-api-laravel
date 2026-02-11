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
        DB::transaction(function () {
            // Run submission status: 0→'closed', 1→'open', 2→'lcc_only'
            DB::statement("
            UPDATE formats
            SET run_submission_status_new = CASE run_submission_status::integer
                WHEN 0 THEN 'closed'
                WHEN 1 THEN 'open'
                WHEN 2 THEN 'lcc_only'
                ELSE 'closed'
            END
        ");

            // Map submission status: 0→'closed', 1→'open', 2→'open_chimps'
            DB::statement("
            UPDATE formats
            SET map_submission_status_new = CASE map_submission_status::integer
                WHEN 0 THEN 'closed'
                WHEN 1 THEN 'open'
                WHEN 2 THEN 'open_chimps'
                ELSE 'closed'
            END
        ");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            // Restore integer columns from string values (in case strings were modified)
            // Run submission status: 'closed'→0, 'open'→1, 'lcc_only'→2
            DB::statement("
                UPDATE formats
                SET run_submission_status = CASE run_submission_status_new
                    WHEN 'closed' THEN 0
                    WHEN 'open' THEN 1
                    WHEN 'lcc_only' THEN 2
                    ELSE 0
                END::integer
            ");

            // Map submission status: 'closed'→0, 'open'→1, 'open_chimps'→2
            DB::statement("
                UPDATE formats
                SET map_submission_status = CASE map_submission_status_new
                    WHEN 'closed' THEN 0
                    WHEN 'open' THEN 1
                    WHEN 'open_chimps' THEN 2
                    ELSE 0
                END::integer
            ");

            // Clear the new columns
            DB::statement("UPDATE formats SET run_submission_status_new = NULL");
            DB::statement("UPDATE formats SET map_submission_status_new = NULL");
        });
    }
};
