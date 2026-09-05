<?php

namespace App\Filament\Widgets;

use App\Models\Zona;
use Filament\Widgets\ChartWidget;

class ZonasDonutChart extends ChartWidget
{
    protected static ?string $heading = 'Distribuição de lotes por Zona Urbana';

    /** Widget do módulo imobiliário (docs/Modulos_Permissoes.txt) */
    public static function canView(): bool
    {
        return \App\Support\Modulos::ativo('imobiliario');
    }

    /** Abaixo da linha bairros + logradouros, largura total (D8, 2026-09-05) */
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $tenantId = \Filament\Facades\Filament::getTenant()->id;

        // Pega as zonas e conta quantos lotes tem dentro de cada uma
        $zonas = Zona::withCount('lotes')->where('tenant_id', $tenantId)->get();

        // Monta a Label: "ZCS - Zona Comercial Sul"
        $labels = $zonas->map(fn ($z) => "{$z->sigla} - {$z->name}")->toArray();
        $dados = $zonas->pluck('lotes_count')->toArray();

        // MÁGICA: Pega a cor RGB do banco e converte pro gráfico!
        $cores = $zonas->map(function ($z) {
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
