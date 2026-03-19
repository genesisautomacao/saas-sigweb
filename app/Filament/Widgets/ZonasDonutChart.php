<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Zona;

class ZonasDonutChart extends ChartWidget
{
    protected static ?string $heading = 'Distribuição de lotes por Zona Urbana';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected function getData(): array
    {
        $tenantId = \Filament\Facades\Filament::getTenant()->id;
        
        // Pega as zonas e conta quantos lotes tem dentro de cada uma
        $zonas = Zona::withCount('lotes')->where('tenant_id', $tenantId)->get();

        // Monta a Label: "ZCS - Zona Comercial Sul"
        $labels = $zonas->map(fn($z) => "{$z->sigla} - {$z->name}")->toArray();
        $dados = $zonas->pluck('lotes_count')->toArray();
        
        // MÁGICA: Pega a cor RGB do banco e converte pro gráfico!
        $cores = $zonas->map(function($z) {
            $rgb = str_replace(['(', ')'], '', $z->rgb ?? '150,150,150');
            return "rgba({$rgb}, 0.8)";
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Qtd de Lotes',
                    'data' => $dados,
                    'backgroundColor' => $cores,
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}