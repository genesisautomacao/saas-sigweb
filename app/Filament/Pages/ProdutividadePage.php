<?php

namespace App\Filament\Pages;

use App\Models\SetorFiscal;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class ProdutividadePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Produtividade';
    protected static ?string $title = 'Relatório de Produtividade';
    protected static ?string $navigationGroup = 'Administração';
    protected static ?int $navigationSort = 97;
    protected static string $view = 'filament.pages.produtividade';

    public int $tenantId = 0;
    public string $dataFiltro = '';
    public ?int $quadraId = null;
    public ?int $setorId = null;

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $this->tenantId = $tenant?->id ?? 0;
        $this->dataFiltro = today()->toDateString();
    }

    #[Computed]
    public function resumo(): array
    {
        if (!$this->tenantId) {
            return ['total' => 0, 'coletados' => 0, 'pendentes' => 0, 'inconformidades' => 0, 'nao_visitados' => 0, 'percentual' => 0];
        }

        $q = DB::table('lotes')->where('tenant_id', $this->tenantId)->whereNull('deleted_at');

        if ($this->quadraId) {
            $q->where('quadra_id', $this->quadraId);
        }

        if ($this->setorId) {
            $q->whereRaw(
                "ST_Intersects(ST_Centroid(geo::geometry), (SELECT geo::geometry FROM setores_fiscais WHERE id = ?))",
                [$this->setorId]
            );
        }

        $rows = (clone $q)->selectRaw("
            count(*) as total,
            sum(case when status_cadastro = 'coletado' then 1 else 0 end) as coletados,
            sum(case when status_cadastro = 'pendente' then 1 else 0 end) as pendentes,
            sum(case when status_cadastro = 'inconformidade' then 1 else 0 end) as inconformidades,
            sum(case when status_cadastro = 'nao_visitado' then 1 else 0 end) as nao_visitados
        ")->first();

        $total = (int) $rows->total;

        return [
            'total'          => $total,
            'coletados'      => (int) $rows->coletados,
            'pendentes'      => (int) $rows->pendentes,
            'inconformidades'=> (int) $rows->inconformidades,
            'nao_visitados'  => (int) $rows->nao_visitados,
            'percentual'     => $total > 0 ? round((int) $rows->coletados * 100 / $total, 1) : 0,
        ];
    }

    #[Computed]
    public function porCadastrador(): array
    {
        if (!$this->tenantId) {
            return [];
        }

        return DB::table('lotes as l')
            ->join('users as u', 'u.id', '=', 'l.coletado_por_id')
            ->where('l.tenant_id', $this->tenantId)
            ->whereNull('l.deleted_at')
            ->whereNotNull('l.coletado_por_id')
            ->when($this->quadraId, fn ($q) => $q->where('l.quadra_id', $this->quadraId))
            ->when($this->setorId, fn ($q) => $q->whereRaw(
                "ST_Intersects(ST_Centroid(l.geo::geometry), (SELECT geo::geometry FROM setores_fiscais WHERE id = ?))",
                [$this->setorId]
            ))
            ->selectRaw("
                u.id as user_id, u.name,
                sum(case when date(l.coletado_em) = ? then 1 else 0 end) as coletados_hoje,
                count(*) as coletados_total
            ", [$this->dataFiltro])
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('coletados_hoje')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }

    #[Computed]
    public function porQuadra(): array
    {
        if (!$this->tenantId) {
            return [];
        }

        return DB::table('lotes as l')
            ->leftJoin('quadras as q', 'q.id', '=', 'l.quadra_id')
            ->where('l.tenant_id', $this->tenantId)
            ->whereNull('l.deleted_at')
            ->when($this->quadraId, fn ($q) => $q->where('l.quadra_id', $this->quadraId))
            ->when($this->setorId, fn ($q) => $q->whereRaw(
                "ST_Intersects(ST_Centroid(l.geo::geometry), (SELECT geo::geometry FROM setores_fiscais WHERE id = ?))",
                [$this->setorId]
            ))
            ->selectRaw("
                l.quadra_id,
                coalesce(q.name, 'S/Q') as quadra_nome,
                count(*) as total,
                sum(case when l.status_cadastro = 'coletado' then 1 else 0 end) as coletados,
                round(
                    sum(case when l.status_cadastro = 'coletado' then 1 else 0 end) * 100.0 / nullif(count(*), 0),
                    1
                ) as percentual
            ")
            ->groupBy('l.quadra_id', 'q.name')
            ->orderByDesc('coletados')
            ->limit(20)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }
}
