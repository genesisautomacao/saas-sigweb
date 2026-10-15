<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R84-6 — Testadas de lote ganham o número da prefeitura (sequential_id) e o `code`,
 * como as demais camadas: é o que permite os modos Atualizar/Substituir da Importação
 * GIS e a exportação reimportável. Backfill por prefeitura na ordem de criação (id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lote_testadas', function (Blueprint $table) {
            $table->unsignedBigInteger('sequential_id')->nullable()->after('tenant_id');
            $table->uuid('code')->nullable()->after('sequential_id');
        });

        DB::statement('
            UPDATE lote_testadas t SET sequential_id = n.seq
            FROM (SELECT id, ROW_NUMBER() OVER (PARTITION BY tenant_id ORDER BY id) AS seq FROM lote_testadas) n
            WHERE n.id = t.id
        ');
        DB::statement('UPDATE lote_testadas SET code = gen_random_uuid() WHERE code IS NULL');

        DB::statement('CREATE UNIQUE INDEX lote_testadas_tenant_seq_unique ON lote_testadas (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS lote_testadas_tenant_seq_unique');

        Schema::table('lote_testadas', function (Blueprint $table) {
            $table->dropColumn(['sequential_id', 'code']);
        });
    }
};
