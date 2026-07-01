<?php

namespace App\Filament\Pages;

use App\Traits\HasTenantModule;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class PainelSocialPage extends Page
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'social';
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';
    protected static ?string $navigationLabel = 'Painel Social';
    protected static ?string $title = 'Painel Social — Situação Cadastral';
    protected static ?string $navigationGroup = 'Módulo Social';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.pages.painel-social';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_painel_social') ?? false;
    }

    public int $tenantId = 0;

    /** Rótulos e cores por situação cadastral (item 098). */
    public const SITUACOES = [
        'cadastrado'            => ['label' => 'Cadastrado',        'cor' => '#9ca3af'],
        'beneficiado'           => ['label' => 'Beneficiado',       'cor' => '#16a34a'],
        'aprovado'              => ['label' => 'Aprovado',          'cor' => '#10b981'],
        'sorteado'              => ['label' => 'Sorteado',          'cor' => '#3b82f6'],
        'nao_localizado'        => ['label' => 'Não Localizado',    'cor' => '#ef4444'],
        'apresentou_documentos' => ['label' => 'Apresentou Docs',   'cor' => '#f59e0b'],
    ];

    public function mount(): void
    {
        $this->tenantId = Filament::getTenant()?->id ?? 0;
    }

    /** Famílias georreferenciadas (moradia atual ou empreendimento de benefício). */
    #[Computed]
    public function familias(): array
    {
        if (!$this->tenantId) {
            return [];
        }

        $rows = DB::select("
            SELECT c.id,
                   c.situacao_cadastro,
                   p.name AS rf,
                   ST_X(ST_Centroid(COALESCE(u.geo, e.geo))) AS lon,
                   ST_Y(ST_Centroid(COALESCE(u.geo, e.geo))) AS lat
            FROM cadastros_sociais c
            JOIN pessoas p ON p.id = c.pessoa_id
            LEFT JOIN unidade_imobiliarias u ON u.id = c.unidade_imobiliaria_id
            LEFT JOIN empreendimentos e ON e.id = c.empreendimento_id
            WHERE c.tenant_id = ?
              AND c.deleted_at IS NULL
              AND COALESCE(u.geo, e.geo) IS NOT NULL
        ", [$this->tenantId]);

        return array_map(fn($r) => [
            'id'        => $r->id,
            'rf'        => $r->rf,
            'situacao'  => $r->situacao_cadastro ?? 'cadastrado',
            'lat'       => (float) $r->lat,
            'lon'       => (float) $r->lon,
        ], $rows);
    }

    /** Distribuição por situação (para o gráfico pizza), sobre as famílias localizadas. */
    #[Computed]
    public function distribuicao(): array
    {
        $counts = [];
        foreach (array_keys(self::SITUACOES) as $key) {
            $counts[$key] = 0;
        }
        foreach ($this->familias as $f) {
            $s = $f['situacao'];
            $counts[$s] = ($counts[$s] ?? 0) + 1;
        }
        return $counts;
    }
}
