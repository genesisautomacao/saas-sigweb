<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Lote;
use App\Models\UnidadeImobiliaria;
use App\Models\Pessoa;
use App\Models\SolicitacaoManutencao;

class DashboardStats extends BaseWidget
{
    protected static ?int $sort = 1; // Fica no topo da tela

    protected function getStats(): array
    {
        $tenantId = \Filament\Facades\Filament::getTenant()->id;

        return [
            Stat::make('Lotes no Mapa', Lote::where('tenant_id', $tenantId)->whereNotNull('geo')->count())
                ->description('Polígonos desenhados')
                ->descriptionIcon('heroicon-m-map')
                ->color('success'),

            Stat::make('Unidades Sincronizadas', UnidadeImobiliaria::where('tenant_id', $tenantId)->whereNotNull('dados_tributarios')->count())
                ->description('Integradas com a Prefeitura')
                ->descriptionIcon('heroicon-m-cloud-arrow-down')
                ->color('info'),

            Stat::make('Pessoas Cadastradas', Pessoa::where('tenant_id', $tenantId)->count())
                ->description('Munícipes no banco de dados')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Manutenções Pendentes', SolicitacaoManutencao::where('tenant_id', $tenantId)->whereIn('status', ['pendente', 'analise', 'aprovada_os'])->count())
                ->description('Postes e Árvores')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('danger'),
        ];
    }
}