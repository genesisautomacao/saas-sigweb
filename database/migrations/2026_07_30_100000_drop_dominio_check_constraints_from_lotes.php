<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * R67-2 — libera o vocabulário municipal de `ocupacao` e `situacao_quadra`.
 *
 * As colunas nasceram como `enum()`, que no PostgreSQL vira varchar + CHECK com a
 * lista fixa do sistema. Como o município agora define a própria lista em
 * `campo_dominios` (CampoDominioService), qualquer valor fora de
 * baldio|construido / meio_quadra|esquina|encravado estourava
 * "violates check constraint lotes_ocupacao_check" ao salvar o lote.
 *
 * ⚠️ `lotes_status_cadastro_check` NÃO é removido de propósito: `status_cadastro` é
 * estrutural (o sistema raciocina com ele — cor do mapa, produtividade, app) e não
 * faz parte dos campos white-label.
 */
return new class extends Migration
{
    private const RESTRICOES = [
        'lotes_ocupacao_check',
        'lotes_situacao_quadra_check',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::RESTRICOES as $restricao) {
            DB::statement("ALTER TABLE lotes DROP CONSTRAINT IF EXISTS {$restricao}");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // NOT VALID: recria a restrição sem validar as linhas existentes — senão o
        // rollback falharia em qualquer município que já tenha gravado valor próprio.
        DB::statement("ALTER TABLE lotes ADD CONSTRAINT lotes_ocupacao_check
            CHECK (ocupacao::text = ANY (ARRAY['baldio','construido']::text[])) NOT VALID");

        DB::statement("ALTER TABLE lotes ADD CONSTRAINT lotes_situacao_quadra_check
            CHECK (situacao_quadra::text = ANY (ARRAY['meio_quadra','esquina','encravado']::text[])) NOT VALID");
    }
};
