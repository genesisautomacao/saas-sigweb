<?php

namespace App\Services\Coleta;

use App\Models\CampoCustomizado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PoC Tangará — KIT INICIAL de campos customizados.
 *
 * Depois da refatoração (lista aprovada em docs/campos_imobiliario_para_aprovacao.txt),
 * atributos como "Tipo de Edificação" e "Nº de Pavimentos" deixaram de ser colunas e
 * passam a existir por prefeitura. Este kit garante que nenhum município comece com a
 * edificação "vazia" — os itens 16 e 43 do edital citam esses campos.
 *
 * Idempotente: só cria o que não existe (por tenant + entidade + slug). O município pode
 * renomear rótulo, trocar opções ou desativar o que não usar.
 */
class KitCamposCustomizadosService
{
    /**
     * Definições do kit. Opções são o PONTO DE PARTIDA (editáveis pelo município).
     */
    public const KIT = [
        ['entidade' => 'lote', 'slug' => 'situacao_quadra', 'label' => 'Situação na Quadra', 'tipo' => 'selecao', 'opcoes' => ['Meio de Quadra', 'Esquina', 'Encravado'], 'na_coleta' => true],

        ['entidade' => 'edificacao', 'slug' => 'tipo_edificacao', 'label' => 'Tipo de Edificação', 'tipo' => 'selecao', 'opcoes' => ['Residencial', 'Comercial', 'Industrial', 'Misto', 'Outro'], 'na_coleta' => true],
        ['entidade' => 'edificacao', 'slug' => 'pavimento', 'label' => 'Nº de Pavimentos', 'tipo' => 'numero', 'opcoes' => [], 'na_coleta' => true],
        ['entidade' => 'edificacao', 'slug' => 'tp_construcao', 'label' => 'Tipo de Construção (material)', 'tipo' => 'selecao', 'opcoes' => ['Alvenaria', 'Madeira', 'Mista', 'Outro'], 'na_coleta' => true],
        ['entidade' => 'edificacao', 'slug' => 'estado_conservacao', 'label' => 'Estado de Conservação', 'tipo' => 'selecao', 'opcoes' => ['Ruim', 'Regular', 'Bom', 'Ótimo'], 'na_coleta' => true],

        // Onda 8 (App de Coletas) — divergência de proprietário apontada em campo:
        // o coletor NUNCA regrava o cadastro oficial; informa aqui e a prefeitura
        // valida no Relatório de Validação da Coleta (decisão do usuário, 2026-08-04).
        ['entidade' => 'unidade', 'slug' => 'proprietario_divergente', 'label' => 'Proprietário divergente (informado na coleta)', 'tipo' => 'texto', 'opcoes' => [], 'na_coleta' => true],
        ['entidade' => 'unidade', 'slug' => 'cpf_cnpj_divergente', 'label' => 'CPF/CNPJ divergente (informado na coleta)', 'tipo' => 'texto', 'opcoes' => [], 'na_coleta' => true],

        ['entidade' => 'secao_logradouro', 'slug' => 'tipo_pavimentacao', 'label' => 'Tipo de Pavimentação', 'tipo' => 'selecao', 'opcoes' => ['Asfalto', 'Paralelepípedo', 'Concreto', 'Cascalho', 'Terra', 'Outro'], 'na_coleta' => false],

        ['entidade' => 'meio_fio', 'slug' => 'material', 'label' => 'Material', 'tipo' => 'selecao', 'opcoes' => ['Concreto', 'Granito', 'Outro'], 'na_coleta' => false],
        ['entidade' => 'meio_fio', 'slug' => 'estado_conservacao', 'label' => 'Estado de Conservação', 'tipo' => 'selecao', 'opcoes' => ['Ruim', 'Regular', 'Bom'], 'na_coleta' => false],
    ];

    /**
     * Aplica o kit a um tenant. Retorna quantos campos foram criados.
     *
     * @param  bool  $derivarOpcoes  também incorpora às opções os valores DISTINTOS já
     *                               gravados em dados_customizados (municípios com dado
     *                               legado migrado das antigas colunas).
     */
    public static function aplicar(int $tenantId, bool $derivarOpcoes = false): int
    {
        $criados = 0;

        foreach (self::KIT as $ordem => $definicao) {
            $existe = CampoCustomizado::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('entidade', $definicao['entidade'])
                ->where('slug', $definicao['slug'])
                ->whereNull('deleted_at')
                ->exists();

            if ($existe) {
                continue;
            }

            $opcoes = $definicao['opcoes'];

            if ($derivarOpcoes && $definicao['tipo'] === 'selecao') {
                $opcoes = self::mesclarComDadoReal($tenantId, $definicao['entidade'], $definicao['slug'], $opcoes);
            }

            CampoCustomizado::create([
                'tenant_id' => $tenantId,
                'entidade' => $definicao['entidade'],
                'slug' => $definicao['slug'],
                'label' => $definicao['label'],
                'tipo' => $definicao['tipo'],
                'opcoes' => $opcoes ?: null,
                'obrigatorio' => false,
                'na_coleta' => $definicao['na_coleta'],
                'ordem' => $ordem,
                'ativo' => true,
            ]);

            $criados++;
        }

        CampoCustomizadoService::limparCache();

        return $criados;
    }

    /**
     * Une as opções default com os valores distintos já gravados no JSON da entidade —
     * assim o dado legado (ex.: "Alvenaria (0)" migrado da coluna antiga) continua
     * selecionável em vez de órfão.
     */
    protected static function mesclarComDadoReal(int $tenantId, string $entidade, string $slug, array $default): array
    {
        $tabela = CampoCustomizadoService::ENTIDADE_TABELA[$entidade] ?? null;

        if (! $tabela || ! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'dados_customizados')) {
            return $default;
        }

        $reais = DB::table($tabela)
            ->where('tenant_id', $tenantId)
            ->whereRaw("dados_customizados->>'{$slug}' IS NOT NULL")
            ->whereRaw("dados_customizados->>'{$slug}' <> ''")
            ->selectRaw("DISTINCT dados_customizados->>'{$slug}' AS v")
            ->orderBy('v')
            ->pluck('v')
            ->all();

        // Default primeiro (vocabulário recomendado), depois o legado que não coincide.
        return array_values(array_unique(array_merge($default, $reais)));
    }
}
