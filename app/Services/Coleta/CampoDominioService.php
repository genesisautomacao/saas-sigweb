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
     * Padrões do sistema (rótulo + lista de CHAVES imutáveis). Chave = entidade.campo.
     *
     * Depois da refatoração da PoC Tangará (lista aprovada em
     * docs/campos_imobiliario_para_aprovacao.txt) só restam aqui os campos fixos COM
     * lista de valores governada pelo sistema — todo o resto ou é texto/número/foto
     * (sem lista) ou virou campo customizado da prefeitura:
     *   - edificacao: TODOS os atributos viraram campos customizados (o vocabulário
     *     vinha do sistema tributário — "Alvenaria (0)", "Pavimento 1"...);
     *   - unidade: as 13 colunas fiscais foram removidas (dado vive em dados_tributarios);
     *   - lote.situacao_quadra: virou campo customizado (a lista varia por município);
     *   - secoes.tipo_pavimentacao / meio_fios.*: viraram campos customizados.
     */
    public const PADROES = [
        'lote' => [
            'ocupacao' => [
                'label' => 'Ocupação do Lote',
                // Binário estrutural: itens 42/51/60 do edital cravam "(Baldio ou
                // Construído)" e a automação de excluir edificação depende disso.
                'opcoes' => ['baldio' => 'Baldio', 'construido' => 'Construído'],
            ],
        ],
        'secao_logradouro' => [
            'lado' => [
                'label' => 'Lado da Seção',
                // Item 44 do edital; o T2.2 deriva o lado por geometria.
                'opcoes' => ['par' => 'Par', 'impar' => 'Ímpar', 'ambos' => 'Ambos'],
            ],
        ],
        'lote_testada' => [
            'tipo' => [
                'label' => 'Tipo da Testada',
                'opcoes' => ['principal' => 'Principal', 'secundaria' => 'Secundária', 'lateral' => 'Lateral', 'fundos' => 'Fundos'],
            ],
        ],
    ];

    /**
     * Entidades cujos campos padrão entram no BOLETIM do app de coleta.
     * Só o lote tem campo padrão coletável (ocupacao); o resto do boletim
     * vem dos campos customizados de lote/edificacao/unidade.
     */
    public const ENTIDADES_NA_COLETA = ['lote'];

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
     * Opções do campo: `chave do sistema => rótulo do município`.
     *
     * ⚠️ As CHAVES vêm SEMPRE do PADROES e são imutáveis (decisão D6 da PoC Tangará).
     * O município só renomeia o RÓTULO de cada valor — nunca inventa nem remove valor.
     * Precisa de um valor que não existe? Cria um campo customizado (CampoCustomizado).
     *
     * Antes desta correção o município gravava uma lista solta de rótulos e o Select
     * salvava o RÓTULO na coluna — foi assim que a base ganhou `esquina` E `Esquina`
     * como valores distintos do mesmo conceito, o que faria o mapa temático por valores
     * únicos pintar duas classes para a mesma coisa.
     */
    public static function opcoes(string $entidade, string $campo, ?int $tenantId = null): array
    {
        $padrao = self::PADROES[$entidade][$campo]['opcoes'] ?? [];

        if (empty($padrao)) {
            return [];
        }

        $custom = self::dominio($entidade, $campo, $tenantId)?->opcoes ?? [];

        $saida = [];
        foreach ($padrao as $chave => $rotuloPadrao) {
            $saida[$chave] = filled($custom[$chave] ?? null) ? $custom[$chave] : $rotuloPadrao;
        }

        return $saida;
    }

    /**
     * Traduz um valor vindo de FORA (push do app, importação, payload legado) para a
     * CHAVE do sistema. Aceita a chave em si, o rótulo do município ou o rótulo padrão,
     * ignorando caixa e acento.
     *
     * Valor desconhecido volta como está — nunca destruímos dado que não sabemos mapear;
     * ele fica visível via rotuloValor() e o município decide o que fazer.
     */
    public static function normalizarValor(string $entidade, string $campo, ?string $valor, ?int $tenantId = null): ?string
    {
        if (blank($valor)) {
            return $valor;
        }

        $opcoes = self::opcoes($entidade, $campo, $tenantId);

        if (empty($opcoes) || array_key_exists($valor, $opcoes)) {
            return $valor;
        }

        $alvo = self::comparavel($valor);

        // 1) bate com o rótulo vigente (do município ou o padrão herdado)
        foreach ($opcoes as $chave => $rotulo) {
            if (self::comparavel($rotulo) === $alvo || self::comparavel((string) $chave) === $alvo) {
                return (string) $chave;
            }
        }

        // 2) bate com o rótulo padrão do sistema (município renomeou depois do dado entrar)
        foreach (self::PADROES[$entidade][$campo]['opcoes'] ?? [] as $chave => $rotuloPadrao) {
            if (self::comparavel($rotuloPadrao) === $alvo) {
                return (string) $chave;
            }
        }

        return $valor;
    }

    /** Forma comparável de um texto: sem acento, sem caixa, sem espaço nas pontas. */
    public static function comparavel(string $texto): string
    {
        return mb_strtolower(trim(\Illuminate\Support\Str::ascii($texto)));
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
     * Inputs da UNIDADE nos modais Cadastrar/Editar (HasLoteActions): os campos fixos
     * que restaram + os campos customizados do município.
     *
     * As 13 colunas fiscais foram removidas (lista aprovada da PoC Tangará) — o que o
     * município quer ver do tributário vira campo customizado alimentado pelo de/para,
     * e aparece aqui automaticamente via CampoCustomizadoService::componentes().
     */
    public static function componentesFiscaisUnidade(?int $tenantId = null): array
    {
        return array_merge(
            [
                Forms\Components\TextInput::make('nome_edificio')
                    ->label('Nome do Edifício / Condomínio')
                    ->maxLength(255),
            ],
            CampoCustomizadoService::componentes('unidade', 'dados_customizados', $tenantId),
        );
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

            $opcoes = self::opcoes($entidade, $campo, $tenantId);

            $saida[$campo] = [
                'label' => self::label($entidade, $campo, $tenantId),
                // `opcoes` = o que o cadastrador LÊ na tela (rótulos do município).
                // `valores` = o que deve ser ENVIADO no push, na mesma ordem (chave do sistema).
                // O app publicado ainda manda o rótulo; o push traduz de volta com
                // CampoDominioService::normalizarValor(), então os dois formatos funcionam.
                'opcoes' => array_values($opcoes),
                'valores' => array_map('strval', array_keys($opcoes)),
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
