<?php

namespace App\Filament\Widgets;

use App\Models\Logradouro;
use App\Support\Modulos;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;

/**
 * Pizza dos logradouros por extensão (m) — par do gráfico de bairros na linha da
 * base cartográfica do dashboard (D8, 2026-09-05). Lê `extensao_geo` (cache PostGIS,
 * ver `gis:recalcular-metadata`); os maiores são nomeados e o resto vira "Outros"
 * para a pizza continuar legível em municípios com centenas de ruas.
 */
class LogradourosPieChart extends ChartWidget
{
    protected static ?string $heading = 'Extensão por Logradouro (m)';

    public static function canView(): bool
    {
        return Modulos::ativo('base_cartografica');
    }

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    private const MAXIMO_NOMEADOS = 12;

    private const PALETA = [
        '#2563eb', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#06b6d4',
        '#f97316', '#84cc16', '#ec4899', '#14b8a6', '#6366f1', '#a16207',
    ];

    protected function getData(): array
    {
        $tenantId = Filament::getTenant()->id;

        $logradouros = Logradouro::where('tenant_id', $tenantId)
            ->whereNotNull('extensao_geo')
            ->where('extensao_geo', '>', 0)
            ->orderByDesc('extensao_geo')
            ->get(['name', 'extensao_geo']);

        $principais = $logradouros->take(self::MAXIMO_NOMEADOS);
        $outros = $logradouros->slice(self::MAXIMO_NOMEADOS);

        $labels = $principais->map(fn ($l) => Str::limit($l->name ?? 'Sem nome', 30))->values()->all();
        $dados = $principais->map(fn ($l) => round((float) $l->extensao_geo, 2))->values()->all();
        $cores = array_map(fn ($i) => self::PALETA[$i % count(self::PALETA)], array_keys($labels));

        if ($outros->isNotEmpty()) {
            $labels[] = 'Outros ('.$outros->count().' logradouros)';
            $dados[] = round((float) $outros->sum('extensao_geo'), 2);
            $cores[] = '#9ca3af';
        }

        return [
            'datasets' => [
                [
                    'label' => 'Extensão (m)',
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
        return 'pie';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                    'labels' => ['boxWidth' => 12, 'font' => ['size' => 10]],
                ],
            ],
        ];
    }
}
