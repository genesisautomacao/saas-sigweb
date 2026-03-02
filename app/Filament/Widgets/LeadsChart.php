<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class LeadsChart extends ChartWidget
{
    protected static ?string $heading = 'Desempenho de Prospecções';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        /** @var \App\Models\User $user */
        $user = \Filament\Facades\Filament::auth()->user();

        $labels = [];
        $dataCapturados = [];

        // Monta os últimos 6 meses dinamicamente
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $labels[] = $month->translatedFormat('M'); // Ex: Jan, Fev, Mar

            // Busca os leads reais deste mês
            $query = Lead::where('tenant_id', $tenant?->id)
                ->whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year);

            // Aplica a restrição de vendedor, se ele a possuir
            if ($user && $user->hasPermissionTo('view_my_leads')) {
                $query->where('seller_id', $user->seller?->id);
            }

            $dataCapturados[] = $query->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Leads Capturados',
                    'data' => $dataCapturados,
                    'borderColor' => '#4f46e5',
                    'backgroundColor' => 'rgba(79, 70, 229, 0.2)',
                    'fill' => true,
                ],
                // O segundo array de "Vendas Fechadas" eu removi temporariamente
                // até termos a aba/status de faturamento para cruzar os dados reais.
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}