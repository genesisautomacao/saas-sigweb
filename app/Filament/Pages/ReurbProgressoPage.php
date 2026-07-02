<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

/**
 * Dashboard de progresso dos Processos Digitais (item 224 / B11).
 * Mostra a distribuição dos processos pela etapa atual (com a cor da etapa),
 * cards de resumo e filtro por fluxo. Complementa a coloração no mapa (item 223).
 */
class ReurbProgressoPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Progresso dos Processos';
    protected static ?string $title = 'Progresso dos Processos Digitais';
    protected static ?string $navigationGroup = 'Processos Digitais';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.pages.reurb-progresso-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_processos_progresso') ?? false;
    }

    public int $tenantId = 0;
    public ?int $fluxoId = null;

    public function mount(): void
    {
        $this->tenantId = Filament::getTenant()?->id ?? 0;
    }

    #[Computed]
    public function fluxos(): array
    {
        if (!$this->tenantId) {
            return [];
        }

        return DB::table('bpmn_fluxos')
            ->where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    #[Computed]
    public function resumo(): array
    {
        $vazio = ['total' => 0, 'concluidos' => 0, 'em_andamento' => 0, 'aguardando' => 0, 'pendentes' => 0, 'percentual' => 0];

        if (!$this->tenantId) {
            return $vazio;
        }

        $rows = DB::table('processos_digitais')
            ->where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'rascunho')
            ->when($this->fluxoId, fn ($q) => $q->where('bpmn_fluxo_id', $this->fluxoId))
            ->selectRaw("
                count(*) as total,
                sum(case when status = 'concluido' then 1 else 0 end) as concluidos,
                sum(case when status = 'em_andamento' then 1 else 0 end) as em_andamento,
                sum(case when status = 'aguardando_solicitante' then 1 else 0 end) as aguardando,
                sum(case when status = 'pendente_correcao' then 1 else 0 end) as pendentes
            ")
            ->first();

        $total = (int) $rows->total;

        return [
            'total'        => $total,
            'concluidos'   => (int) $rows->concluidos,
            'em_andamento' => (int) $rows->em_andamento,
            'aguardando'   => (int) $rows->aguardando,
            'pendentes'    => (int) $rows->pendentes,
            'percentual'   => $total > 0 ? round((int) $rows->concluidos * 100 / $total, 1) : 0,
        ];
    }

    #[Computed]
    public function porEtapa(): array
    {
        if (!$this->tenantId) {
            return [];
        }

        return DB::table('processos_digitais as p')
            ->leftJoin('bpmn_etapas as e', 'e.id', '=', 'p.etapa_atual_id')
            ->where('p.tenant_id', $this->tenantId)
            ->whereNull('p.deleted_at')
            ->where('p.status', '!=', 'rascunho')
            ->when($this->fluxoId, fn ($q) => $q->where('p.bpmn_fluxo_id', $this->fluxoId))
            ->selectRaw("
                coalesce(e.nome, 'Sem etapa') as etapa,
                coalesce(e.cor_mapa, '#9ca3af') as cor,
                coalesce(e.ordem, 999) as ordem,
                count(*) as total
            ")
            ->groupBy('e.nome', 'e.cor_mapa', 'e.ordem')
            ->orderBy('ordem')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }
}
