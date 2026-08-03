<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PoC Tangará — execução da lista aprovada (docs/campos_imobiliario_para_aprovacao.txt).
 *
 * Regra da lista: campo alimentado pelo sistema tributário não é campo fixo do SIGWEB.
 * Antes de cada DROP, o dado existente é copiado para `dados_customizados` (slug = nome
 * da coluna) — exceto as colunas fiscais da unidade, cujo dado JÁ vive íntegro em
 * `dados_tributarios` (foram derivadas dele; o write-through dos modais mantinha os dois).
 *
 * Também cria as 2 colunas aprovadas: `secoes_logradouro.lado` e `unidade.nome_edificio`.
 */
return new class extends Migration
{
    /**
     * Colunas que migram para dados_customizados antes do DROP.
     * tabela => [coluna => slug de destino]
     */
    private const MIGRAR_E_REMOVER = [
        'edificacoes' => [
            'tipo' => 'tipo_edificacao', // renomeado: slug mais explícito (decisão do usuário)
            'tp_construcao' => 'tp_construcao',
            'caracteristica_construcao' => 'caracteristica_construcao',
            'estado_conservacao' => 'estado_conservacao',
            'pavimento' => 'pavimento',
        ],
        'secoes_logradouro' => [
            'tipo_pavimentacao' => 'tipo_pavimentacao',
        ],
        'meio_fios' => [
            'material' => 'material',
            'estado_conservacao' => 'estado_conservacao',
        ],
        'quadras' => [
            'setor_codigo' => 'setor_codigo',
        ],
        'bairros' => [
            'setor' => 'setor',
        ],
        'loteamentos' => [
            'setor' => 'setor',
        ],
        'setores_fiscais' => [
            'descricao' => 'descricao',
        ],
    ];

    /**
     * Colunas removidas SEM cópia — o dado de origem continua em outro lugar.
     * unidade: dados_tributarios (JSON bruto). lotes: endereço é cópia da unidade.
     */
    private const REMOVER_DIRETO = [
        'unidade_imobiliarias' => [
            'tipo_construcao', 'descricao_classificacao', 'face', 'fracao_ideal',
            'area_edificacao', 'area_total_edificacao', 'valor_venal_lote',
            'valor_venal_edificacao', 'valor_metro_terreno', 'valor_metro_edificacao',
            'valor_imposto_territorial', 'valor_imposto_predial', 'valor_total_imposto',
        ],
        'lotes' => [
            'tipo_logradouro', 'logradouro', 'cep',
        ],
    ];

    public function up(): void
    {
        // ── 1. copiar dado -> dados_customizados e dropar ───────────────────
        foreach (self::MIGRAR_E_REMOVER as $tabela => $colunas) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }

            $existentes = array_filter(
                $colunas,
                fn ($slug, $coluna) => Schema::hasColumn($tabela, $coluna),
                ARRAY_FILTER_USE_BOTH
            );

            if ($existentes === []) {
                continue;
            }

            // jsonb_build_object('slug', coluna, ...) só com valores preenchidos;
            // merge por cima do que já houver em dados_customizados.
            $pares = [];
            $where = [];
            foreach ($existentes as $coluna => $slug) {
                $pares[] = "'{$slug}', {$coluna}::text";
                $where[] = "({$coluna} IS NOT NULL AND {$coluna}::text <> '')";
            }

            $objeto = 'jsonb_strip_nulls(jsonb_build_object(' . implode(', ', $pares) . '))';
            $condicao = implode(' OR ', $where);

            $afetados = DB::update("
                UPDATE {$tabela}
                SET dados_customizados = COALESCE(dados_customizados, '{}'::jsonb) || {$objeto}
                WHERE {$condicao}
            ");

            echo "  ── {$tabela}: {$afetados} registro(s) copiados para dados_customizados" . PHP_EOL;

            Schema::table($tabela, function (Blueprint $table) use ($existentes) {
                $table->dropColumn(array_keys($existentes));
            });
        }

        // ── 2. remoções diretas (dado já vive em outro lugar) ──────────────
        foreach (self::REMOVER_DIRETO as $tabela => $colunas) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }

            $existentes = array_values(array_filter(
                $colunas,
                fn ($coluna) => Schema::hasColumn($tabela, $coluna)
            ));

            if ($existentes !== []) {
                Schema::table($tabela, fn (Blueprint $table) => $table->dropColumn($existentes));
                echo "  ── {$tabela}: " . count($existentes) . ' coluna(s) removidas (dado preservado na origem)' . PHP_EOL;
            }
        }

        // ── 3. colunas novas aprovadas ──────────────────────────────────────
        if (! Schema::hasColumn('secoes_logradouro', 'lado')) {
            Schema::table('secoes_logradouro', function (Blueprint $table) {
                // par | impar | ambos — lista governada pelo sistema (item 44 do edital)
                $table->string('lado', 10)->nullable()->after('codigo');
            });
        }

        if (! Schema::hasColumn('unidade_imobiliarias', 'nome_edificio')) {
            Schema::table('unidade_imobiliarias', function (Blueprint $table) {
                // Nome próprio do edifício/condomínio (itens 5 e 3-3) — identificação,
                // não vocabulário do fornecedor; por isso é coluna, não campo customizado.
                $table->string('nome_edificio')->nullable()->after('numero_imovel');
            });

            // Backfill do JSON quando a importação já trouxe o nome.
            DB::statement("
                UPDATE unidade_imobiliarias
                SET nome_edificio = dados_tributarios->>'nome_edificio'
                WHERE dados_tributarios->>'nome_edificio' IS NOT NULL
                  AND dados_tributarios->>'nome_edificio' <> ''
            ");

            DB::statement('CREATE INDEX IF NOT EXISTS unidade_imobiliarias_nome_edificio_trgm ON unidade_imobiliarias USING GIN (nome_edificio gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        // Sem retorno automático: o dado copiado segue em dados_customizados e o fiscal
        // em dados_tributarios. Recriar as colunas vazias só mascararia o estado real.
        DB::statement('DROP INDEX IF EXISTS unidade_imobiliarias_nome_edificio_trgm');

        if (Schema::hasColumn('unidade_imobiliarias', 'nome_edificio')) {
            Schema::table('unidade_imobiliarias', fn (Blueprint $t) => $t->dropColumn('nome_edificio'));
        }

        if (Schema::hasColumn('secoes_logradouro', 'lado')) {
            Schema::table('secoes_logradouro', fn (Blueprint $t) => $t->dropColumn('lado'));
        }
    }
};
