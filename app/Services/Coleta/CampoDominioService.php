<?php

namespace App\Services\Coleta;

use App\Models\CampoDominio;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Component;

/**
 * R67-2 — campos PADRÃO "white-label": cada município define o rótulo, a lista de valores,
 * se usa o campo e se ele é exigido na coleta. A COLUNA no banco não muda — só a
 * apresentação —, então relatórios, PGV, mapa e o contrato do app seguem estáveis.
 *
 * Sem configuração do município, tudo cai no padrão do sistema (const PADROES) — é o
 * comportamento atual, então nada muda para quem não configurar.
 */
class CampoDominioService
{
    /**
     * Padrões do sistema (rótulo + opções de hoje). Chave = entidade.campo.
     * `opcoes` vazio = campo sem lista (numérico/texto livre, ex.: pavimento).
     */
    public const PADROES = [
        'lote' => [
            'ocupacao' => [
                'label' => 'Ocupação do Lote',
                'opcoes' => ['baldio' => 'Baldio', 'construido' => 'Construído'],
            ],
            'situacao_quadra' => [
                'label' => 'Situação na Quadra',
                'opcoes' => ['meio_quadra' => 'Meio de Quadra', 'esquina' => 'Esquina', 'encravado' => 'Encravado'],
            ],
        ],
        'edificacao' => [
            'tipo' => [
                'label' => 'Finalidade / Uso',
                'opcoes' => ['Residencial' => 'Residencial', 'Comercial' => 'Comercial', 'Industrial' => 'Industrial', 'Misto' => 'Misto', 'Outro' => 'Outro'],
            ],
            'tp_construcao' => [
                'label' => 'Tipo de Construção (material)',
                'opcoes' => ['Alvenaria' => 'Alvenaria', 'Madeira' => 'Madeira', 'Mista' => 'Mista', 'Outro' => 'Outro'],
            ],
            'caracteristica_construcao' => [
                'label' => 'Característica da Construção',
                'opcoes' => [],
            ],
            'estado_conservacao' => [
                'label' => 'Estado de Conservação',
                'opcoes' => ['Ruim' => 'Ruim', 'Regular' => 'Regular', 'Médio' => 'Médio', 'Bom' => 'Bom'],
            ],
            'pavimento' => [
                'label' => 'Nº de Pavimentos',
                'opcoes' => [],
            ],
        ],

        // Unidade imobiliária: as 13 colunas fiscais (projeção do sistema tributário).
        // Aqui o município customiza SÓ rótulo + visibilidade — nunca lista de valores,
        // porque os valores chegam da importação/sincronização tributária, não do usuário.
        'unidade' => [
            'tipo_construcao' => ['label' => 'Tipo de Construção', 'opcoes' => []],
            'descricao_classificacao' => ['label' => 'Classificação', 'opcoes' => []],
            'face' => ['label' => 'Face da Quadra', 'opcoes' => []],
            'fracao_ideal' => ['label' => 'Fração Ideal', 'opcoes' => []],
            'area_edificacao' => ['label' => 'Área Edificação', 'opcoes' => []],
            'area_total_edificacao' => ['label' => 'Área Total Edif.', 'opcoes' => []],
            'valor_venal_lote' => ['label' => 'Valor Venal Terreno', 'opcoes' => []],
            'valor_venal_edificacao' => ['label' => 'Valor Venal Edificação', 'opcoes' => []],
            'valor_metro_terreno' => ['label' => 'Valor m² Terreno', 'opcoes' => []],
            'valor_metro_edificacao' => ['label' => 'Valor m² Edificação', 'opcoes' => []],
            'valor_imposto_territorial' => ['label' => 'IPTU Territorial', 'opcoes' => []],
            'valor_imposto_predial' => ['label' => 'IPTU Predial', 'opcoes' => []],
            'valor_total_imposto' => ['label' => 'IPTU Total', 'opcoes' => []],
        ],
    ];

    /**
     * Entidades cujos campos padrão entram no BOLETIM do app de coleta.
     * `unidade` fica de fora: os dados fiscais são somente-leitura no app
     * (o cadastrador não digita valor venal — isso vem do sistema tributário).
     */
    public const ENTIDADES_NA_COLETA = ['lote', 'edificacao'];

    /** Cache por request: [tenantId][entidade][campo] => CampoDominio */
    protected static array $cache = [];

    /** Rótulo do campo neste município (fallback = rótulo padrão do sistema). */
    public static function label(string $entidade, string $campo, ?int $tenantId = null): string
    {
        $dominio = self::dominio($entidade, $campo, $tenantId);

        return filled($dominio?->label)
            ? $dominio->label
            : (self::PADROES[$entidade][$campo]['label'] ?? ucfirst(str_replace('_', ' ', $campo)));
    }

    /**
     * Opções do campo neste município (fallback = lista padrão).
     * Lista do município é uma lista simples de strings (valor = rótulo).
     */
    public static function opcoes(string $entidade, string $campo, ?int $tenantId = null): array
    {
        $dominio = self::dominio($entidade, $campo, $tenantId);
        $custom = $dominio?->opcoes ?? [];

        if (! empty($custom)) {
            // TagsInput devolve lista simples; valor = rótulo (o que é gravado na coluna).
            return array_combine($custom, $custom) ?: [];
        }

        return self::PADROES[$entidade][$campo]['opcoes'] ?? [];
    }

    /**
     * Rótulo de um VALOR já gravado na coluna (tabelas, PDFs, exports, fichas).
     *
     * Vale tanto para o vocabulário do município quanto para o legado: valor que não
     * está mais na lista é exibido como está (ex.: 'construido' num município que
     * trocou a lista) em vez de virar "—" e sumir do relatório.
     */
    public static function rotuloValor(string $entidade, string $campo, ?string $valor, ?int $tenantId = null): ?string
    {
        if (blank($valor)) {
            return null;
        }

        $opcoes = self::opcoes($entidade, $campo, $tenantId);

        return $opcoes[$valor]
            ?? self::PADROES[$entidade][$campo]['opcoes'][$valor]
            ?? $valor;
    }

    /** O município usa este campo? (false = ocultar dos formulários e do boletim). */
    public static function visivel(string $entidade, string $campo, ?int $tenantId = null): bool
    {
        return self::dominio($entidade, $campo, $tenantId)?->visivel ?? true;
    }

    /** O campo é exigido no boletim de coleta do app? */
    public static function obrigatorioColeta(string $entidade, string $campo, ?int $tenantId = null): bool
    {
        return self::dominio($entidade, $campo, $tenantId)?->obrigatorio_coleta ?? false;
    }

    /** O campo aparece no boletim de coleta do app? */
    public static function naColeta(string $entidade, string $campo, ?int $tenantId = null): bool
    {
        return self::dominio($entidade, $campo, $tenantId)?->na_coleta ?? true;
    }

    /**
     * Aplica rótulo + opções + visibilidade do município num componente Filament.
     * Uso: CampoDominioService::aplicar(Select::make('tp_construcao'), 'edificacao', 'tp_construcao')
     */
    public static function aplicar(Component $component, string $entidade, string $campo, ?int $tenantId = null): Component
    {
        $component->label(self::label($entidade, $campo, $tenantId));

        if (method_exists($component, 'options')) {
            $opcoes = self::opcoes($entidade, $campo, $tenantId);

            if (! empty($opcoes)) {
                // O valor já gravado entra na lista quando o município mudou o vocabulário:
                // sem isso o Select abriria em branco e o registro antigo perderia o dado.
                $component->options(function ($state) use ($opcoes): array {
                    if (filled($state) && is_scalar($state) && ! array_key_exists($state, $opcoes)) {
                        $opcoes[$state] = $state.' (valor atual)';
                    }

                    return $opcoes;
                });
            }
        }

        if (! self::visivel($entidade, $campo, $tenantId)) {
            // hidden + dehydratedWhenHidden: some da tela SEM apagar o valor já gravado
            // (⚠️ hidden puro não desidrata — mesma pegadinha do motor de processos).
            $component->hidden()->dehydrated(true)->dehydratedWhenHidden();
        }

        return $component;
    }

    /**
     * Os 13 inputs fiscais da UNIDADE com o rótulo/visibilidade do município aplicados.
     * Fonte única para os modais "Cadastrar/Editar Unidade" (HasLoteActions) — campo
     * oculto usa hidden()->dehydrated(true), preservando o write-through para o JSON.
     */
    public static function componentesFiscaisUnidade(?int $tenantId = null): array
    {
        $numericos = [
            'fracao_ideal' => [],
            'area_edificacao' => ['suffix' => 'm²'],
            'area_total_edificacao' => ['suffix' => 'm²'],
            'valor_venal_lote' => ['prefix' => 'R$'],
            'valor_venal_edificacao' => ['prefix' => 'R$'],
            'valor_metro_terreno' => ['prefix' => 'R$'],
            'valor_metro_edificacao' => ['prefix' => 'R$'],
            'valor_imposto_territorial' => ['prefix' => 'R$'],
            'valor_imposto_predial' => ['prefix' => 'R$'],
            'valor_total_imposto' => ['prefix' => 'R$'],
        ];

        $componentes = [];

        foreach (array_keys(self::PADROES['unidade']) as $campo) {
            $input = Forms\Components\TextInput::make($campo);

            if (array_key_exists($campo, $numericos)) {
                $input->numeric();
                if (isset($numericos[$campo]['prefix'])) {
                    $input->prefix($numericos[$campo]['prefix']);
                }
                if (isset($numericos[$campo]['suffix'])) {
                    $input->suffix($numericos[$campo]['suffix']);
                }
            } else {
                $input->maxLength(255);
            }

            if ($campo === 'valor_total_imposto') {
                $input->columnSpan(['default' => 3]);
            }

            $componentes[] = self::aplicar($input, 'unidade', $campo, $tenantId);
        }

        return $componentes;
    }

    /** Rótulos de todos os campos padrão de uma entidade (cabeçalhos de export/PDF). */
    public static function rotulos(string $entidade, ?int $tenantId = null): array
    {
        $saida = [];
        foreach (array_keys(self::PADROES[$entidade] ?? []) as $campo) {
            $saida[$campo] = self::label($entidade, $campo, $tenantId);
        }

        return $saida;
    }

    /** Configuração completa de uma entidade para o app (boletim). */
    public static function paraApp(string $entidade, ?int $tenantId = null): array
    {
        $saida = [];
        foreach (array_keys(self::PADROES[$entidade] ?? []) as $campo) {
            if (! self::visivel($entidade, $campo, $tenantId) || ! self::naColeta($entidade, $campo, $tenantId)) {
                continue;
            }

            $saida[$campo] = [
                'label' => self::label($entidade, $campo, $tenantId),
                'opcoes' => array_values(self::opcoes($entidade, $campo, $tenantId)),
                'obrigatorio' => self::obrigatorioColeta($entidade, $campo, $tenantId),
            ];
        }

        return $saida;
    }

    protected static function dominio(string $entidade, string $campo, ?int $tenantId = null): ?CampoDominio
    {
        $tenantId ??= Filament::getTenant()?->id;

        if (! $tenantId) {
            return null;
        }

        if (! isset(self::$cache[$tenantId])) {
            self::$cache[$tenantId] = CampoDominio::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->get()
                ->groupBy('entidade')
                ->map(fn ($grupo) => $grupo->keyBy('campo'))
                ->all();
        }

        return self::$cache[$tenantId][$entidade][$campo] ?? null;
    }

    /** Limpa o cache (usado após salvar a configuração). */
    public static function limparCache(): void
    {
        self::$cache = [];
    }
}
