<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PoC Tangará — fundação dos campos customizados.
 *
 * (1) json -> JSONB nas colunas de dado livre.
 *     `json` no PostgreSQL é texto validado: NÃO aceita índice GIN. Como quase todo
 *     atributo do cadastro passa a viver em `dados_customizados`, e o item 76 exige
 *     filtrar/tematizar/gerar estatística por esses campos, sem `jsonb` cada filtro
 *     vira varredura completa com parse linha a linha.
 *
 * (2) `dados_customizados` nas demais camadas.
 *     O item 75 pede campo customizado "vinculando o mesmo a sua respectiva Camada
 *     (Layer)" — hoje só 3 de ~13 camadas aceitam. Sem isso, remover colunas como
 *     `meio_fios.material` deixaria o município sem para onde levar o dado.
 *
 * (3) Índice GIN em cada `dados_customizados`, que é o que dá escala ao item 76.
 *
 * (4) pg_trgm + GIN trigram na busca unificada: hoje ela faz ILIKE '%termo%' sem
 *     nenhum índice que a sirva.
 */
return new class extends Migration
{
    /** Colunas já existentes que são `json` e precisam virar `jsonb`. */
    private const PARA_JSONB = [
        ['lotes', 'dados_customizados'],
        ['edificacoes', 'dados_customizados'],
        ['unidade_imobiliarias', 'dados_customizados'],
        ['unidade_imobiliarias', 'dados_tributarios'],
    ];

    /** Camadas que passam a aceitar campos customizados (item 75). */
    private const NOVAS_CAMADAS = [
        'quadras',
        'bairros',
        'logradouros',
        'secoes_logradouro',
        'loteamentos',
        'zonas',
        'perimetros_urbanos',
        'setores_fiscais',
        'meio_fios',
        'lote_testadas',
    ];

    /** Busca textual da busca unificada: coluna => índice trigram. */
    private const TRIGRAM = [
        ['pessoas', 'name'],
        ['pessoas', 'cpf'],
        ['pessoas', 'cnpj'],
        ['unidade_imobiliarias', 'logradouro_nome'],
    ];

    public function up(): void
    {
        // ── 1. json -> jsonb ────────────────────────────────────────────────
        foreach (self::PARA_JSONB as [$tabela, $coluna]) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, $coluna)) {
                continue;
            }

            $tipo = DB::selectOne(
                "SELECT data_type FROM information_schema.columns WHERE table_name = ? AND column_name = ?",
                [$tabela, $coluna]
            );

            if (($tipo->data_type ?? null) === 'json') {
                DB::statement("ALTER TABLE {$tabela} ALTER COLUMN {$coluna} TYPE jsonb USING {$coluna}::jsonb");
            }
        }

        // `lotes.dados_vistoria` morre aqui: superado pelo boletim configurável (R67-3),
        // que compõe o formulário de campos padrão + customizados, ambos com casa própria.
        if (Schema::hasTable('lotes') && Schema::hasColumn('lotes', 'dados_vistoria')) {
            Schema::table('lotes', fn (Blueprint $t) => $t->dropColumn('dados_vistoria'));
        }

        // ── 2. dados_customizados nas demais camadas ────────────────────────
        foreach (self::NOVAS_CAMADAS as $tabela) {
            if (! Schema::hasTable($tabela) || Schema::hasColumn($tabela, 'dados_customizados')) {
                continue;
            }

            DB::statement("ALTER TABLE {$tabela} ADD COLUMN dados_customizados jsonb NULL");
        }

        // ── 3. índice GIN em todo dados_customizados ────────────────────────
        $comCustom = array_merge(
            ['lotes', 'edificacoes', 'unidade_imobiliarias'],
            self::NOVAS_CAMADAS
        );

        foreach ($comCustom as $tabela) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'dados_customizados')) {
                continue;
            }

            DB::statement("CREATE INDEX IF NOT EXISTS {$tabela}_dados_customizados_gin ON {$tabela} USING GIN (dados_customizados)");
        }

        // JSON bruto do tributário também vira pesquisável (de/para, BIC, extras).
        if (Schema::hasColumn('unidade_imobiliarias', 'dados_tributarios')) {
            DB::statement('CREATE INDEX IF NOT EXISTS unidade_imobiliarias_dados_tributarios_gin ON unidade_imobiliarias USING GIN (dados_tributarios)');
        }

        // ── 4. pg_trgm + índices para o ILIKE da busca unificada ────────────
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        foreach (self::TRIGRAM as [$tabela, $coluna]) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, $coluna)) {
                continue;
            }

            DB::statement("CREATE INDEX IF NOT EXISTS {$tabela}_{$coluna}_trgm ON {$tabela} USING GIN ({$coluna} gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        foreach (self::TRIGRAM as [$tabela, $coluna]) {
            DB::statement("DROP INDEX IF EXISTS {$tabela}_{$coluna}_trgm");
        }

        DB::statement('DROP INDEX IF EXISTS unidade_imobiliarias_dados_tributarios_gin');

        $comCustom = array_merge(['lotes', 'edificacoes', 'unidade_imobiliarias'], self::NOVAS_CAMADAS);

        foreach ($comCustom as $tabela) {
            DB::statement("DROP INDEX IF EXISTS {$tabela}_dados_customizados_gin");
        }

        foreach (self::NOVAS_CAMADAS as $tabela) {
            if (Schema::hasTable($tabela) && Schema::hasColumn($tabela, 'dados_customizados')) {
                Schema::table($tabela, fn (Blueprint $t) => $t->dropColumn('dados_customizados'));
            }
        }

        // dados_vistoria não é recriada: era campo morto, sem uso definido.
        // jsonb -> json também não é revertido (jsonb é superconjunto funcional).
    }
};
