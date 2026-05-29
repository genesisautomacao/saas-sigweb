<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class MonitoramentoCampoPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationLabel = 'Monitoramento de Campo';
    protected static ?string $title = 'Monitoramento em Tempo Real';
    protected static ?string $navigationGroup = 'Coleta cadastral';
    protected static ?int $navigationSort = 30;
    protected static string $view = 'filament.pages.monitoramento-campo';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_monitoramento_campo') ?? false;
    }

    public int $tenantId = 0;

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $this->tenantId = $tenant?->id ?? 0;
    }

    #[Computed]
    public function cadastradores(): array
    {
        if (!$this->tenantId) {
            return [];
        }

        $dez = now()->subMinutes(10);

        return DB::table('cadastrador_locations as cl')
            ->join('users as u', 'u.id', '=', 'cl.user_id')
            ->where('cl.tenant_id', $this->tenantId)
            ->where('cl.updated_at', '>=', $dez)
            ->select([
                'cl.user_id',
                'u.name',
                'u.email',
                'cl.lat',
                'cl.lon',
                'cl.updated_at',
            ])
            ->orderByDesc('cl.updated_at')
            ->get()
            ->map(function ($row) {
                $coletadosHoje = DB::table('lotes')
                    ->where('coletado_por_id', $row->user_id)
                    ->where('tenant_id', $this->tenantId)
                    ->whereDate('coletado_em', today())
                    ->count();

                return [
                    'user_id'        => $row->user_id,
                    'name'           => $row->name,
                    'email'          => $row->email,
                    'lat'            => (float) $row->lat,
                    'lon'            => (float) $row->lon,
                    'updated_at'     => $row->updated_at,
                    'coletados_hoje' => $coletadosHoje,
                ];
            })
            ->toArray();
    }
}
