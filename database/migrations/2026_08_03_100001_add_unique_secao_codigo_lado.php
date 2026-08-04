<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T1.3 (PoC Tangará, item 44) — trava de unicidade da seção.
 *
 * Impede duplicar a MESMA seção do MESMO lado do mesmo logradouro, liberando os
 * dois lados (código igual + lado diferente = ok, é o desenho normal: seção 0-120
 * par e seção 0-120 ímpar). Parcial em deleted_at para conviver com soft delete.
 * Como no Postgres NULL ≠ NULL, seções sem código nunca colidem — importação de
 * base legada sem código passa limpo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS secoes_logradouro_codigo_lado_unique
            ON secoes_logradouro (tenant_id, logradouro_id, codigo, lado)
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS secoes_logradouro_codigo_lado_unique');
    }
};
