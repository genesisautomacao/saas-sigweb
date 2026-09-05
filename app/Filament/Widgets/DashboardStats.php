<?php

namespace App\Filament\Widgets;

use App\Models\Lote;
use App\Models\Pessoa;
use App\Models\SolicitacaoManutencao;
use App\Models\UnidadeImobiliaria;
use App\Support\Modulos;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Cards do dashboard — cada card pertence a um módulo (docs/Modulos_Permissoes.txt):
 * só aparecem os dos módulos ativos; sem nenhum, o widget some.
 */
class DashboardStats extends BaseWidget
{
    protected static ?int $sort = 1; // Fica no topo da tela

    public static function canView(): bool
    {
        return Modulos::algumAtivo(['imobiliario', 'administrativo', 'manutencao']);
    }

    protected function getStats(): array
    {
        $tenantId = \Filament\Facades\Filament::getTenant()->id;
        $stats = [];

        if (Modulos::ativo('imobiliario')) {
            $stats[] = Stat::make('Lotes no Mapa', Lote::where('tenant_id', $tenantId)->whereNotNull('geo')->count())
                ->description('Polígonos desenhados')
                ->descriptionIcon('heroicon-m-map')
                ->color('success');

            $stats[] = Stat::make('Unidades Sincronizadas', UnidadeImobiliaria::where('tenant_id', $tenantId)->whereNotNull('dados_tributarios')->count())
                ->description('Integradas com a Prefeitura')
                ->descriptionIcon('heroicon-m-cloud-arrow-down')
                ->color('info');
        }

        if (Modulos::ativo('administrativo')) {
            $stats[] = Stat::make('Pessoas Cadastradas', Pessoa::where('tenant_id', $tenantId)->count())
                ->description('Munícipes no banco de dados')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary');
        }

        if (Modulos::ativo('manutencao')) {
            $stats[] = Stat::make('Manutenções Pendentes', SolicitacaoManutencao::where('tenant_id', $tenantId)->whereIn('status', ['pendente', 'analise', 'aprovada_os'])->count())
                ->description('Postes e Árvores')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('danger');
        }

        return $stats;
    }
}
