<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * No PostgreSQL a FK não cria índice na coluna referenciadora; sem ele,
     * cada linha apagada em lotes dispara um seq scan na tabela filha para
     * resolver o ON DELETE (cascade/set null) — um DELETE em massa trava.
     */
    private const INDICES = [
        'processos_digitais'   => 'lote_id',
        'edificacoes'          => 'lote_id',
        'unidade_imobiliarias' => 'lote_id',
        'viabilidade_emissoes' => 'lote_id',
        'lote_testadas'        => 'lote_id',
        'cadastros_sociais'    => 'terreno_lote_id',
        'pgv_amostras'         => 'lote_id',
        'pgv_configuracoes'    => 'lote_paradigma_id',
    ];

    public function up(): void
    {
        foreach (self::INDICES as $tabela => $coluna) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$tabela}_{$coluna}_index ON {$tabela} ({$coluna})");
        }
    }

    public function down(): void
    {
        foreach (self::INDICES as $tabela => $coluna) {
            DB::statement("DROP INDEX IF EXISTS {$tabela}_{$coluna}_index");
        }
    }
};
