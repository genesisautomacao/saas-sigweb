<?php

namespace App\Services\Coleta;

use App\Models\Tenant;
use App\Models\User;

/**
 * R67-3 — configuração do boletim de coleta entregue ao app.
 * O app monta o formulário inteiro a partir deste payload (rótulos, listas e campos
 * customizados do município + região do cadastrador), sem precisar de release do app
 * quando o município muda um vocabulário.
 */
class ColetaConfigService
{
    /**
     * Campos base do LOTE que podem ser exigidos no boletim.
     * Refatoração PoC Tangará: situacao_quadra virou campo customizado (kit) e a
     * observação passou a pertencer à COLETA (coleta_imobiliaria.observacao).
     */
    public const CAMPOS_BASE_LOTE = [
        'ocupacao' => 'Ocupação do lote',
        'observacao' => 'Observação da coleta',
        'foto_frontal' => 'Foto frontal',
        'foto_lateral_esq' => 'Foto lateral esquerda',
        'foto_lateral_dir' => 'Foto lateral direita',
    ];

    /**
     * Campos base da EDIFICAÇÃO — só a área construída restou como campo fixo;
     * os atributos descritivos são campos customizados (kit inicial).
     */
    public const CAMPOS_BASE_EDIFICACAO = [
        'area_geo' => 'Área construída',
    ];

    /**
     * 2026-08-04 — dados exibidos ao coletor como SOMENTE LEITURA (conferência em
     * campo; ele não edita — divergência vai em campo customizado). Por entidade;
     * na unidade entram TAMBÉM as 13 colunas fiscais visíveis (rótulo white-label).
     * O número do lote fica fora: é o título/identidade da ficha, sempre visível.
     */
    public const CAMPOS_LEITURA = [
        'lote' => [
            'area_geo' => 'Área do lote (m²)',
            'main_facade_length' => 'Testada principal (m)',
        ],
        // edificacao.area_geo NÃO entra aqui: é campo base com os 3 estados
        // (Não usar / Apenas leitura / Preencher) no próprio Boletim.
        'unidade' => [
            'endereco' => 'Endereço (logradouro e número)',
            'inscricao_imobiliaria' => 'Inscrição imobiliária',
            'codigo_imovel_tributario' => 'Código do imóvel (tributário)',
            'nome_edificio' => 'Nome do edifício',
            'proprietario_nome' => 'Proprietário atual (nome)',
            'proprietario_cpf_cnpj' => 'Proprietário atual (CPF/CNPJ)',
        ],
    ];

    /** Seleção default por entidade (= o que o app sempre mostrou até aqui). */
    public const LEITURA_PADRAO = [
        'lote' => ['area_geo'],
        'unidade' => [
            'endereco', 'inscricao_imobiliaria', 'proprietario_nome', 'proprietario_cpf_cnpj',
            'descricao_classificacao', 'area_edificacao', 'valor_total_imposto',
        ],
    ];

    public static function config(Tenant $tenant, User $user): array
    {
        $tenantId = $tenant->id;
        $baseConfig = self::baseConfig($tenant);

        return [
            // Compat com o app publicado: lista dos campos base EXIGIDOS (visíveis+obrigatórios)
            'campos_base' => self::exigidosLegado($baseConfig),

            // 2026-08-04 — campos base com o MESMO par de flags dos demais
            // (aparece no app + obrigatório no boletim), configurado no Boletim de Coleta.
            'campos_base_config' => $baseConfig,

            // Campos padrão com o rótulo/lista do município (white-label)
            'campos_padrao' => [
                'lote' => CampoDominioService::paraApp('lote', $tenantId),
                'edificacao' => CampoDominioService::paraApp('edificacao', $tenantId),
            ],

            // Campos criados pelo município
            'campos_customizados' => [
                'lote' => CampoCustomizadoService::paraApp('lote', $tenantId),
                'edificacao' => CampoCustomizadoService::paraApp('edificacao', $tenantId),
                'unidade' => CampoCustomizadoService::paraApp('unidade', $tenantId),
            ],

            // Dados exibidos como LEITURA no app, por entidade (escolhidos no Boletim)
            'leitura' => [
                'lote' => self::leitura('lote', $tenant),
                'unidade' => self::leitura('unidade', $tenant),
            ],

            // Região atribuída (null = sem restrição; ausência de quadras = não baixa nada)
            'regiao' => ColetaRegiaoService::resumoRegiao($tenantId, $user->id),
        ];
    }

    /**
     * Opções de leitura de uma entidade para o Boletim ([campo => rótulo]).
     * Na unidade entram também as colunas fiscais VISÍVEIS (rótulo white-label).
     */
    public static function opcoesLeitura(string $entidade, ?int $tenantId = null): array
    {
        $opcoes = self::CAMPOS_LEITURA[$entidade] ?? [];

        if ($entidade === 'unidade') {
            foreach (array_keys(CampoDominioService::PADROES['unidade'] ?? []) as $campo) {
                if (CampoDominioService::visivel('unidade', $campo, $tenantId)) {
                    $opcoes[$campo] = CampoDominioService::label('unidade', $campo, $tenantId);
                }
            }
        }

        return $opcoes;
    }

    /**
     * Seleção do município ([{campo, label}], na ordem das opções). Nunca configurou
     * (null) = default histórico; lista vazia salva = município não quer nenhum.
     */
    public static function leitura(string $entidade, Tenant $tenant): array
    {
        $selecao = data_get($tenant->data, "coleta_leitura.{$entidade}");
        $selecao = is_array($selecao) ? $selecao : (self::LEITURA_PADRAO[$entidade] ?? []);

        $saida = [];
        foreach (self::opcoesLeitura($entidade, $tenant->id) as $campo => $label) {
            if (in_array($campo, $selecao, true)) {
                $saida[] = ['campo' => $campo, 'label' => $label];
            }
        }

        return $saida;
    }

    /**
     * Flags dos campos BASE (fotos, observação, área construída) por entidade:
     * {entidade: {campo: {na_coleta, obrigatorio}}}. Fonte:
     * `tenant.data['coleta_campos_base_config']` (Boletim de Coleta); default =
     * visível + obrigatoriedade herdada da lista legada `coleta_campos_base`.
     * Campos que têm domínio próprio (ex.: lote.ocupacao) ficam FORA — são
     * governados pelo CampoDominio.
     */
    public static function baseConfig(Tenant $tenant): array
    {
        $salvo = (array) data_get($tenant->data, 'coleta_campos_base_config', []);
        $legado = (array) data_get($tenant->data, 'coleta_campos_base', []);

        $saida = [];

        foreach (['lote' => self::CAMPOS_BASE_LOTE, 'edificacao' => self::CAMPOS_BASE_EDIFICACAO] as $entidade => $campos) {
            $saida[$entidade] = [];

            foreach (array_keys($campos) as $campo) {
                if (isset(CampoDominioService::PADROES[$entidade][$campo])) {
                    continue; // governado pelo CampoDominio (Boletim já tem os toggles dele)
                }

                $cfg = (array) data_get($salvo, "{$entidade}.{$campo}", []);

                $saida[$entidade][$campo] = [
                    'na_coleta' => (bool) ($cfg['na_coleta'] ?? true),
                    // true = aparece SOMENTE leitura (3 estados do Boletim, 2026-08-04)
                    'leitura' => (bool) ($cfg['leitura'] ?? false),
                    'obrigatorio' => (bool) ($cfg['obrigatorio']
                        ?? in_array($campo, (array) ($legado[$entidade] ?? []), true)),
                ];
            }
        }

        return $saida;
    }

    /** Lista legada (contrato do app publicado): campo base exigido = visível E obrigatório. */
    protected static function exigidosLegado(array $baseConfig): array
    {
        $saida = ['lote' => [], 'edificacao' => []];

        foreach ($baseConfig as $entidade => $campos) {
            foreach ($campos as $campo => $cfg) {
                // Campo em modo leitura nunca é "exigido" — o coletor não o preenche
                if (($cfg['na_coleta'] ?? true) && ! ($cfg['leitura'] ?? false) && ($cfg['obrigatorio'] ?? false)) {
                    $saida[$entidade][] = $campo;
                }
            }
        }

        return $saida;
    }
}
