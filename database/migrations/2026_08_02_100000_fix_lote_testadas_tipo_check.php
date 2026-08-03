<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PoC Tangará — o CHECK de `lote_testadas.tipo` no banco só aceitava
 * principal/secundaria (versão antiga da migration), mas a lista do sistema
 * (item 42 do edital + PADROES) tem 4 valores: principal, secundaria, lateral, fundos.
 * Recria a constraint com a lista completa. `tipo` é campo FIXO com lista governada
 * pelo sistema — o CHECK fica (como o de status_cadastro), só que correto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lote_testadas')) {
            return;
        }

        DB::statement('ALTER TABLE lote_testadas DROP CONSTRAINT IF EXISTS lote_testadas_tipo_check');
        DB::statement("ALTER TABLE lote_testadas ADD CONSTRAINT lote_testadas_tipo_check CHECK (tipo::text = ANY (ARRAY['principal'::text, 'secundaria'::text, 'lateral'::text, 'fundos'::text]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE lote_testadas DROP CONSTRAINT IF EXISTS lote_testadas_tipo_check');
        DB::statement("ALTER TABLE lote_testadas ADD CONSTRAINT lote_testadas_tipo_check CHECK (tipo::text = ANY (ARRAY['principal'::text, 'secundaria'::text]))");
    }
};
