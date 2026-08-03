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
    /** Entidade (chave de CampoCustomizado::ENTIDADES) => tabela real. */
    public const ENTIDADE_TABELA = [
        'lote' => 'lotes',
        'edificacao' => 'edificacoes',
        'unidade' => 'unidade_imobiliarias',
        'quadra' => 'quadras',
        'bairro' => 'bairros',
        'logradouro' => 'logradouros',
        'secao_logradouro' => 'secoes_logradouro',
        'lote_testada' => 'lote_testadas',
        'loteamento' => 'loteamentos',
        'zona' => 'zonas',
        'perimetro' => 'perimetros_urbanos',
        'setor_fiscal' => 'setores_fiscais',
        'meio_fio' => 'meio_fios',
    ];

    /** Cache por request: [tenantId][entidade] => Collection<CampoCustomizado> */
    protected static array $cache = [];

    /** Cache por request das colunas reais de cada tabela. */
    protected static array $cacheColunas = [];

    /**
     * Colunas reais da tabela — um slug customizado NUNCA pode colidir com elas
     * (senão o import GIS e os forms escreveriam no lugar errado).
     *
     * Lida do schema, não de lista fixa: a lista fixa ficava obsoleta a cada
     * migration e ainda mencionaria colunas já removidas.
     */
    public static function colunasReservadas(string $entidade): array
    {
        $tabela = self::ENTIDADE_TABELA[$entidade] ?? null;

        if (! $tabela || ! \Illuminate\Support\Facades\Schema::hasTable($tabela)) {
            return [];
        }

        return self::$cacheColunas[$tabela] ??= \Illuminate\Support\Facades\Schema::getColumnListing($tabela);
    }

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
