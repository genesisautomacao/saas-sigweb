<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use App\Models\LeadStatus;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class LeadsByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Leads por Status';
    protected static ?int $sort = 2;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getBaseQuery(): Builder
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        $query = Lead::where('tenant_id', $tenant?->id);

        /** @var \App\Models\User $user */
        $user = \Filament\Facades\Filament::auth()->user();

        if ($user && $user->hasPermissionTo('view_my_leads')) {
            $query->where('seller_id', $user->seller?->id);
        }

        return $query;
    }

    protected function getData(): array
    {
        $leadsByStatus = $this->getBaseQuery()
            ->selectRaw('lead_status_id, count(*) as total')
            ->whereNotNull('lead_status_id')
            ->groupBy('lead_status_id')
            ->get();

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($leadsByStatus as $row) {
            $status = LeadStatus::find($row->lead_status_id);
            if ($status) {
                $labels[] = $status->name;
                $data[] = $row->total;
                $colors[] = $status->color ?? '#9ca3af';
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Quantidade',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }
}