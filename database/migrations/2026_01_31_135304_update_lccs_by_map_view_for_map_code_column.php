<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('
            CREATE OR REPLACE VIEW public.lccs_by_map AS
            SELECT DISTINCT ON (c.map_code, cm.format) c.map_code AS map,
                cm.format,
                lcc.leftover,
                lcc.id
            FROM ((public.latest_completions cm
                JOIN public.completions c ON ((c.id = cm.completion)))
                JOIN public.leastcostchimps lcc ON ((cm.lcc = lcc.id)))
            WHERE ((cm.accepted_by IS NOT NULL) AND (cm.deleted_on IS NULL))
            ORDER BY c.map_code, cm.format, lcc.leftover DESC, c.submitted_on
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            CREATE OR REPLACE VIEW public.lccs_by_map AS
            SELECT DISTINCT ON (c.map, cm.format) c.map,
                cm.format,
                lcc.leftover,
                lcc.id
            FROM ((public.latest_completions cm
                JOIN public.completions c ON ((c.id = cm.completion)))
                JOIN public.leastcostchimps lcc ON ((cm.lcc = lcc.id)))
            WHERE ((cm.accepted_by IS NOT NULL) AND (cm.deleted_on IS NULL))
            ORDER BY c.map, cm.format, lcc.leftover DESC, c.submitted_on
        ');
    }
};
