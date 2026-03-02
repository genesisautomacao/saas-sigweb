<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use App\Models\Seller;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    // --- INTELIGÊNCIA DE DADOS (MANAGER VS VENDEDOR) ---
    protected function getBaseQuery(): Builder
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        $query = Lead::where('tenant_id', $tenant?->id);

        /** @var \App\Models\User $user */
        $user = \Filament\Facades\Filament::auth()->user();

        // Se tiver a restrição, filtra apenas para o vendedor logado
        if ($user && $user->hasPermissionTo('view_my_leads')) {
            $query->where('seller_id', $user->seller?->id);
        }

        return $query;
    }

    protected function getStats(): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();

        // 1. Total de Leads
        $totalLeads = $this->getBaseQuery()->count();
        $leadsThisMonth = $this->getBaseQuery()
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        // 2. Membros da Equipe (Vendedores ativos nesta transportadora)
        $totalSellers = Seller::where('tenant_id', $tenant?->id)->where('is_active', true)->count();

        // 3. Leads com conversação recente (últimos 15 dias)
        $activeFollowUps = $this->getBaseQuery()
            ->whereNotNull('last_follow_up_date')
            ->where('last_follow_up_date', '>=', Carbon::now()->subDays(15))
            ->count();

        return [
            Stat::make('Total de Leads (Sua Carteira)', $totalLeads)
                ->description("+ {$leadsThisMonth} novos neste mês")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]), // Mantivemos o seu design do sparkline verde

            Stat::make('Vendedores na Equipe', $totalSellers)
                ->description('Força de vendas ativa')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make('Leads Quentes', $activeFollowUps)
                ->description('Interações recentes')
                ->descriptionIcon('heroicon-m-fire')
                ->color('warning')
                ->chart([17, 16, 14, 15, 14, 13, 12]),
        ];
    }
}