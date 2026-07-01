<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add PostGIS geometry column for the frontage line
        DB::statement("ALTER TABLE lote_testadas ADD COLUMN IF NOT EXISTS geo geometry(MULTILINESTRING, 4326)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_lote_testadas_geo ON lote_testadas USING gist(geo)");

        // Narrow the tipo constraint to principal|secundaria only (table is empty at this point)
        DB::statement("ALTER TABLE lote_testadas DROP CONSTRAINT IF EXISTS lote_testadas_tipo_check");
        DB::statement("ALTER TABLE lote_testadas ADD CONSTRAINT lote_testadas_tipo_check CHECK (tipo::text = ANY(ARRAY['principal'::text, 'secundaria'::text]))");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_lote_testadas_geo");
        DB::statement("ALTER TABLE lote_testadas DROP COLUMN IF EXISTS geo");
        DB::statement("ALTER TABLE lote_testadas DROP CONSTRAINT IF EXISTS lote_testadas_tipo_check");
        DB::statement("ALTER TABLE lote_testadas ADD CONSTRAINT lote_testadas_tipo_check CHECK (tipo::text = ANY(ARRAY['principal'::text, 'secundaria'::text, 'lateral'::text, 'fundos'::text]))");
    }
};
