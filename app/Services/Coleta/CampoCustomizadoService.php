<?php

namespace App\Services\Coleta;

use App\Models\CampoCustomizado;
use Filament\Facades\Filament;
use Filament\Forms;
use Illuminate\Support\Collection;

/**
 * R67-1 — campos customizados por município para lote/edificação/unidade imobiliária.
 * As DEFINIÇÕES ficam em `campos_customizados`; os VALORES na coluna JSON
 * `dados_customizados` da entidade (chave = slug).
 */
class CampoCustomizadoService
{
    /**
     * Colunas reais de cada tabela — um slug customizado NUNCA pode colidir com elas
     * (senão o import GIS e os forms escreveriam no lugar errado).
     */
    public const COLUNAS_RESERVADAS = [
        'lote' => [
            'id', 'tenant_id', 'sequential_id', 'quadra_id', 'zona_id', 'code', 'numero_lote',
            'area_geo', 'area_cadastrada', 'main_facade_length', 'geo', 'foto_frontal',
            'foto_lateral_esq', 'foto_lateral_dir', 'observacao', 'status_cadastro', 'ocupacao',
            'situacao_quadra', 'inconformidade_descricao', 'dados_vistoria', 'dados_customizados',
            'coletado_por_id', 'coletado_em', 'numero_predial_antigo', 'tipo_logradouro',
            'logradouro', 'numero_logradouro', 'cep', 'created_at', 'updated_at', 'deleted_at',
        ],
        'edificacao' => [
            'id', 'tenant_id', 'sequential_id', 'lote_id', 'code', 'tipo', 'tp_construcao',
            'caracteristica_construcao', 'estado_conservacao', 'pavimento', 'area_geo', 'geo',
            'dados_customizados', 'created_at', 'updated_at', 'deleted_at',
        ],
        'unidade' => [
            'id', 'tenant_id', 'sequential_id', 'lote_id', 'proprietario_id', 'code',
            'codigo_imovel_tributario', 'inscricao_imobiliaria', 'geo', 'dados_tributarios',
            'dados_customizados', 'logradouro_nome', 'numero_imovel', 'tipo_construcao',
            'descricao_classificacao', 'face', 'fracao_ideal', 'area_edificacao',
            'area_total_edificacao', 'valor_venal_lote', 'valor_venal_edificacao',
            'valor_metro_terreno', 'valor_metro_edificacao', 'valor_imposto_territorial',
            'valor_imposto_predial', 'valor_total_imposto', 'created_at', 'updated_at', 'deleted_at',
        ],
    ];

    /** Cache por request: [tenantId][entidade] => Collection<CampoCustomizado> */
    protected static array $cache = [];

    /** Definições ativas da entidade, ordenadas. */
    public static function definicoes(string $entidade, ?int $tenantId = null): Collection
    {
        $tenantId ??= Filament::getTenant()?->id;

        if (! $tenantId) {
            return collect();
        }

        if (! isset(self::$cache[$tenantId])) {
            self::$cache[$tenantId] = CampoCustomizado::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('ativo', true)
                ->whereNull('deleted_at')
                ->orderBy('ordem')
                ->orderBy('id')
                ->get()
                ->groupBy('entidade')
                ->all();
        }

        return collect(self::$cache[$tenantId][$entidade] ?? []);
    }

    /**
     * Componentes Filament dos campos customizados (molde ProcessoFormService::camposDaEtapa).
     * State path: `dados_customizados.<slug>` — o cast array do model persiste sozinho.
     */
    public static function componentes(string $entidade, string $prefixo = 'dados_customizados', ?int $tenantId = null): array
    {
        return self::definicoes($entidade, $tenantId)->map(function (CampoCustomizado $campo) use ($prefixo) {
            $nome = $prefixo.'.'.$campo->slug;
            $opcoes = $campo->opcoes ?? [];

            $component = match ($campo->tipo) {
                'numero' => Forms\Components\TextInput::make($nome)->numeric(),
                'data' => Forms\Components\DatePicker::make($nome)->native(false),
                'sim_nao' => Forms\Components\Toggle::make($nome),
                'selecao' => Forms\Components\Select::make($nome)
                    ->options(array_combine($opcoes, $opcoes) ?: [])
                    ->native(false),
                // Múltipla escolha SEMPRE como Select multiple — o CheckboxList tem bug de
                // seleção com state path aninhado (mesmo motivo do motor de processos).
                'multipla' => Forms\Components\Select::make($nome)
                    ->options(array_combine($opcoes, $opcoes) ?: [])
                    ->multiple()
                    ->native(false),
                default => Forms\Components\TextInput::make($nome),
            };

            return $component
                ->label($campo->label)
                ->required($campo->obrigatorio);
        })->all();
    }

    /**
     * Whitelist + cast do payload vindo do app (push) ou do GeoJSON (import GIS):
     * só slugs definidos entram, com o tipo certo.
     */
    public static function filtrarPayload(string $entidade, array $entrada, ?int $tenantId = null): array
    {
        $definicoes = self::definicoes($entidade, $tenantId)->keyBy('slug');
        $saida = [];

        foreach ($entrada as $slug => $valor) {
            $campo = $definicoes->get($slug);

            if (! $campo || $valor === null || $valor === '') {
                continue;
            }

            $saida[$slug] = match ($campo->tipo) {
                'numero' => is_numeric($valor) ? (float) $valor : null,
                'sim_nao' => filter_var($valor, FILTER_VALIDATE_BOOLEAN),
                'multipla' => is_array($valor) ? array_values($valor) : array_map('trim', explode(',', (string) $valor)),
                default => is_array($valor) ? $valor : (string) $valor,
            };

            if ($saida[$slug] === null) {
                unset($saida[$slug]);
            }
        }

        return $saida;
    }

    /** Colunas rotuladas para exports/relatórios: ['Padrão Construtivo' => 'Alto', ...]. */
    public static function colunasExport(string $entidade, ?array $dados, ?int $tenantId = null): array
    {
        $saida = [];

        foreach (self::definicoes($entidade, $tenantId) as $campo) {
            $valor = $dados[$campo->slug] ?? null;

            $saida[$campo->label] = match (true) {
                is_array($valor) => implode(', ', $valor),
                is_bool($valor) => $valor ? 'Sim' : 'Não',
                $valor === null || $valor === '' => '-',
                default => (string) $valor,
            };
        }

        return $saida;
    }

    /** Definições para o app montar o boletim (só as marcadas "na coleta"). */
    public static function paraApp(string $entidade, ?int $tenantId = null): array
    {
        return self::definicoes($entidade, $tenantId)
            ->where('na_coleta', true)
            ->map(fn (CampoCustomizado $c) => [
                'slug' => $c->slug,
                'label' => $c->label,
                'tipo' => $c->tipo,
                'opcoes' => $c->opcoes ?? [],
                'obrigatorio' => (bool) $c->obrigatorio,
                'ordem' => (int) $c->ordem,
            ])
            ->values()
            ->all();
    }

    public static function limparCache(): void
    {
        self::$cache = [];
    }
}
