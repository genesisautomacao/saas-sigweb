<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L1 (PoC Tangará) — Código municipal editável nas entidades de cadastro.
 *
 * O edital pede "Código" explícito nos itens 44 (Logradouro + Seção), 45 (Setor + Quadra),
 * 46 (Distrito), 47 (Setor), 48 (Bairro) e 49 (Zoneamento). Até aqui o sistema só tinha:
 *   - `code`          = UUID interno, inútil como código de cadastro;
 *   - `sequential_id` = número por prefeitura, mas é a CHAVE DE RESOLUÇÃO da importação GIS
 *                       (`resolveRelacionamento` casa por tenant_id + sequential_id) — torná-lo
 *                       editável quebraria a reimportação.
 *
 * Por isso `codigo` é uma coluna NOVA e independente. Nasce nullable porque nenhuma base
 * existente tem esse dado; a prefeitura preenche conforme cadastra/recodifica.
 *
 * Pré-requisito do T2.5 (Recodificação, item 61) — não se recodifica o que não tem código.
 */
return new class extends Migration
{
    /** Entidades que recebem o código municipal. */
    private const TABELAS = [
        'logradouros',
        'secoes_logradouro',
        'bairros',
        'quadras',
        'zonas',
        'perimetros_urbanos',
        'setores_fiscais',
    ];

    public function up(): void
    {
        foreach (self::TABELAS as $tabela) {
            if (! Schema::hasTable($tabela) || Schema::hasColumn($tabela, 'codigo')) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) {
                $table->string('codigo', 50)->nullable()->after('sequential_id');
            });

            // Busca por código é o caso de uso principal (itens 44-49) e sempre dentro do tenant.
            DB::statement("CREATE INDEX {$tabela}_tenant_codigo_index ON {$tabela} (tenant_id, codigo)");
        }
    }

    public function down(): void
    {
        foreach (self::TABELAS as $tabela) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'codigo')) {
                continue;
            }

            DB::statement("DROP INDEX IF EXISTS {$tabela}_tenant_codigo_index");

            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('codigo');
            });
        }
    }
};
