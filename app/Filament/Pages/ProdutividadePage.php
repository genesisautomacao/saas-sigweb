<?php

namespace App\Filament\Pages;

use App\Models\ColetaAtribuicao;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

/**
 * Resumo de Produtividade — acompanhamento das REGIÕES DESIGNADAS (R67-6).
 *
 * O recorte da tela é a atribuição do cadastrador (`coleta_atribuicoes`), não o
 * setor fiscal: filtra-se por período + cadastrador e lista-se cada quadra
 * designada com o percentual já cumprido.
 */
class ProdutividadePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Resumo de Produtividade';

    protected static ?string $title = 'Resumo de Produtividade';

    protected static ?string $navigationGroup = 'Coleta cadastral';

    protected static ?int $navigationSort = 31;

    protected static string $view = 'filament.pages.produtividade';

    public static function canAccess(): bool
    {
        // módulo Coleta Cadastral (D4 — docs/Modulos_Permissoes.txt) + permissão
        return \App\Support\Modulos::ativo('coleta_cadastral')
            && (auth()->user()?->temPermissao('view_produtividade') ?? false);
    }

    public int $tenantId = 0;

    public string $dataInicio = '';

    public string $dataFim = '';

    public ?int $cadastradorId = null;

    public function mount(): void
    {
        $this->tenantId = Filament::getTenant()?->id ?? 0;
        $this->dataInicio = today()->startOfMonth()->toDateString();
        $this->dataFim = today()->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportar_pdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => $this->exportarPdf()),
        ];
    }

    // ------------------------------------------------------------------
    // Filtros
    // ------------------------------------------------------------------

    protected function inicio(): string
    {
        return $this->dataInicio ?: today()->toDateString();
    }

    protected function fim(): string
    {
        return $this->dataFim ?: $this->inicio();
    }

    /** Cadastradores que possuem alguma atribuição de região (lista curta e relevante). */
    #[Computed]
    public function cadastradores(): array
    {
        if (! $this->tenantId) {
            return [];
        }

        return DB::table('coleta_atribuicoes as ca')
            ->join('users as u', 'u.id', '=', 'ca.user_id')
            ->where('ca.tenant_id', $this->tenantId)
            ->whereNull('ca.deleted_at')
            ->distinct()
            ->orderBy('u.name')
            ->pluck('u.name', 'u.id')
            ->all();
    }

    // ------------------------------------------------------------------
    // Dados
    // ------------------------------------------------------------------

    /**
     * Uma única passada: linhas (quadra designada) + resumo do período.
     * `#[Computed]` memoiza no request, então blade e PDF reaproveitam o cálculo.
     *
     * @return array{linhas: array<int, array<string, mixed>>, resumo: array<string, int|float>}
     */
    #[Computed]
    public function dados(): array
    {
        $vazio = [
            'linhas' => [],
            'resumo' => [
                'quadras' => 0, 'total' => 0, 'coletados' => 0, 'atendidos' => 0, 'pendentes' => 0,
                'inconformidades' => 0, 'nao_visitados' => 0, 'no_periodo' => 0, 'percentual' => 0,
            ],
        ];

        if (! $this->tenantId) {
            return $vazio;
        }

        $atribuicoes = $this->atribuicoesDoPeriodo();

        if ($atribuicoes->isEmpty()) {
            return $vazio;
        }

        $quadraIds = $atribuicoes
            ->flatMap(fn (ColetaAtribuicao $a) => $a->quadra_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($quadraIds)) {
            return $vazio;
        }

        $nomes = DB::table('quadras')->whereIn('id', $quadraIds)->pluck('name', 'id');
        $stats = $this->estatisticasPorQuadra($quadraIds);

        $linhas = [];

        foreach ($atribuicoes as $atribuicao) {
            foreach (array_unique(array_map('intval', $atribuicao->quadra_ids ?? [])) as $quadraId) {
                $s = $stats[$quadraId] ?? null;
                $total = (int) ($s->total ?? 0);
                $coletados = (int) ($s->coletados ?? 0);
                $inconformidades = (int) ($s->inconformidades ?? 0);
                // Decisão 2026-08-26: inconformidade é VISITA CONCLUÍDA (o trabalho
                // aconteceu; o desfecho apontou problema) — conta como atendido no
                // % e nos restantes, consistente com o por_cadastrador da API.
                $atendidos = $coletados + $inconformidades;

                $linhas[] = [
                    'quadra_id' => $quadraId,
                    'quadra_nome' => $nomes[$quadraId] ?? ('#'.$quadraId),
                    'cadastrador' => $atribuicao->user?->name ?? '—',
                    'periodo' => $this->rotuloPeriodo($atribuicao),
                    'total' => $total,
                    'coletados' => $coletados,
                    'atendidos' => $atendidos,
                    'no_periodo' => (int) ($s->no_periodo ?? 0),
                    'pendentes' => (int) ($s->pendentes ?? 0),
                    'inconformidades' => $inconformidades,
                    'nao_visitados' => (int) ($s->nao_visitados ?? 0),
                    'percentual' => $total > 0 ? round($atendidos * 100 / $total, 1) : 0.0,
                ];
            }
        }

        // Ordena por cadastrador e, dentro dele, pela quadra em ordem natural
        // (nomes de quadra costumam ser numéricos: "2" antes de "10").
        usort($linhas, function (array $a, array $b): int {
            return strnatcasecmp((string) $a['cadastrador'], (string) $b['cadastrador'])
                ?: strnatcasecmp((string) $a['quadra_nome'], (string) $b['quadra_nome']);
        });

        // O resumo agrega por QUADRA DISTINTA: a mesma quadra em duas atribuições
        // dentro do período não pode contar duas vezes.
        $resumo = [
            'quadras' => count($quadraIds), 'total' => 0, 'coletados' => 0, 'atendidos' => 0,
            'pendentes' => 0, 'inconformidades' => 0, 'nao_visitados' => 0, 'no_periodo' => 0,
            'percentual' => 0,
        ];

        foreach ($stats as $s) {
            $resumo['total'] += (int) $s->total;
            $resumo['coletados'] += (int) $s->coletados;
            $resumo['pendentes'] += (int) $s->pendentes;
            $resumo['inconformidades'] += (int) $s->inconformidades;
            $resumo['nao_visitados'] += (int) $s->nao_visitados;
            $resumo['no_periodo'] += (int) $s->no_periodo;
        }

        // atendidos = coletados + inconformidades (visita concluída)
        $resumo['atendidos'] = $resumo['coletados'] + $resumo['inconformidades'];

        $resumo['percentual'] = $resumo['total'] > 0
            ? round($resumo['atendidos'] * 100 / $resumo['total'], 1)
            : 0;

        return ['linhas' => $linhas, 'resumo' => $resumo];
    }

    /** Atribuições ativas que se SOBREPÕEM ao período filtrado. */
    protected function atribuicoesDoPeriodo(): Collection
    {
        $inicio = $this->inicio();
        $fim = $this->fim();

        return ColetaAtribuicao::query()
            ->with('user:id,name')
            ->where('ativo', true)
            ->when($this->cadastradorId, fn ($q) => $q->where('user_id', $this->cadastradorId))
            ->whereDate('data_inicio', '<=', $fim)
            ->where(fn ($q) => $q->whereNull('data_fim')->orWhereDate('data_fim', '>=', $inicio))
            ->orderBy('data_inicio')
            ->get();
    }

    /** Contagens de lotes por quadra designada (uma query só). */
    protected function estatisticasPorQuadra(array $quadraIds): Collection
    {
        // Refatoração PoC Tangará: coletado_em vive em coleta_imobiliaria (campanha vigente).
        return DB::table('lotes')
            ->leftJoin('coleta_imobiliaria as ci', function ($join) {
                $join->on('ci.coletavel_id', '=', 'lotes.id')
                    ->where('ci.coletavel_type', '=', 'App\\Models\\Lote')
                    ->whereNull('ci.deleted_at');
            })
            ->where('lotes.tenant_id', $this->tenantId)
            ->whereNull('lotes.deleted_at')
            ->whereIn('lotes.quadra_id', $quadraIds)
            ->selectRaw("
                lotes.quadra_id,
                count(distinct lotes.id) as total,
                count(distinct case when lotes.status_cadastro = 'coletado' then lotes.id end) as coletados,
                count(distinct case when lotes.status_cadastro = 'pendente' then lotes.id end) as pendentes,
                count(distinct case when lotes.status_cadastro = 'inconformidade' then lotes.id end) as inconformidades,
                count(distinct case when lotes.status_cadastro = 'nao_visitado' then lotes.id end) as nao_visitados,
                count(distinct case when ci.coletado_em is not null and ci.coletado_em::date between ? and ? then lotes.id end) as no_periodo
            ", [$this->inicio(), $this->fim()])
            ->groupBy('lotes.quadra_id')
            ->get()
            ->keyBy('quadra_id');
    }

    protected function rotuloPeriodo(ColetaAtribuicao $a): string
    {
        $inicio = $a->data_inicio?->format('d/m/Y') ?? '—';

        return $a->data_fim
            ? $inicio.' a '.$a->data_fim->format('d/m/Y')
            : 'desde '.$inicio;
    }

    // ------------------------------------------------------------------
    // Exportação
    // ------------------------------------------------------------------

    public function exportarPdf()
    {
        $dados = $this->dados;

        $pdf = Pdf::loadView('pdf.produtividade-quadras', [
            'tenant' => Filament::getTenant(),
            'linhas' => $dados['linhas'],
            'resumo' => $dados['resumo'],
            'periodo' => \Carbon\Carbon::parse($this->inicio())->format('d/m/Y')
                .' a '.\Carbon\Carbon::parse($this->fim())->format('d/m/Y'),
            'cadastrador' => $this->cadastradorId ? ($this->cadastradores[$this->cadastradorId] ?? null) : null,
            'dataHora' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->output();
            },
            'produtividade-regioes-'.now()->format('YmdHis').'.pdf'
        );
    }
}
