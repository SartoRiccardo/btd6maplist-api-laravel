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
        // Drop the trigger that auto-creates verifications on accept
        DB::unprepared('DROP TRIGGER IF EXISTS tr_set_verif_on_accept ON completions_meta');
        DB::unprepared('DROP FUNCTION IF EXISTS set_verif_on_accept()');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the function and trigger
        DB::unprepared("
            CREATE OR REPLACE FUNCTION set_verif_on_accept()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF (TG_OP = 'INSERT' OR OLD.accepted_by IS NULL) THEN
                    CALL set_comp_as_verification(NEW.id);
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE TRIGGER tr_set_verif_on_accept
            AFTER INSERT OR UPDATE ON completions_meta
            FOR EACH ROW
            WHEN (NEW.accepted_by IS NOT NULL AND NEW.format IN (1, 51))
            EXECUTE PROCEDURE set_verif_on_accept();
        ");
    }
};
