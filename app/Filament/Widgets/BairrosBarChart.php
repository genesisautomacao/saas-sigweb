<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Bairro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str; // <-- Importante para o limitador de texto

class BairrosBarChart extends ChartWidget
{
    protected static ?string $heading = 'Área Territorial por Bairro (m²)';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected function getData(): array
    {
        $tenantId = \Filament\Facades\Filament::getTenant()->id;

        $bairros = Bairro::where('tenant_id', $tenantId)
            ->whereNotNull('geo')
            ->select('name', DB::raw('ST_Area(geo::geography) as area_calc'))
            ->orderByDesc('area_calc')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Metros Quadrados (m²)',
                    'data' => $bairros->map(fn($b) => round($b->area_calc, 2))->toArray(),
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 4,
                ],
            ],
            // Corta a string se passar de 30 caracteres
            'labels' => $bairros->map(fn($b) => Str::limit($b->name, 30))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    // A MÁGICA: Configurações injetadas direto no Chart.js
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y', // Inverte o gráfico de vertical para HORIZONTAL
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                ],
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false, // Oculta a legenda "Metros Quadrados" pois o título já diz o que é
                ],
            ],
        ];
    }
}