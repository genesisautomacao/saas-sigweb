<?php

namespace App\Filament\Pages\Traits;

use App\Models\FaceQuadra;
use App\Models\PgvAmostra;
use App\Models\PgvPolo;
use App\Services\Pgv\PgvFaceCalculoService;
use App\Services\Pgv\PgvRegressaoService;
use App\Services\Pgv\PgvSimulacaoIptuService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

/**
 * Orquestra o Motor da PGV no mapa (itens 225–243):
 * amostras/pólos/faces + regressão + cálculo de faces + simulação de IPTU.
 */
trait HasPgvMotorActions
{
    public array $regressaoPgv = [];
    public array $simulacaoIptu = [];
    // buffers para criar geometria clicada/desenhada
    public ?string $pgvPontoLon = null;
    public ?string $pgvPontoLat = null;
    public ?string $pgvFaceGeoJson = null;

    // ---------- Regressão (230–232) ----------
    #[On('pgv-rodar-regressao')]
    public function rodarRegressaoPgv(): void
    {
        $this->regressaoPgv = app(PgvRegressaoService::class)->calcular($this->tenantId);
        $eq = $this->regressaoPgv['equacao'] ?? null;
        if (!$eq) {
            Notification::make()->title('Sem regressão')
                ->body('Cadastre ao menos 1 pólo e 2 amostras não espúrias.')->warning()->send();
        }
        $this->dispatch('pgv-regressao-resultado', dados: $this->regressaoPgv);
    }

    #[On('pgv-toggle-espuria')]
    public function toggleEspuriaPgv($amostraId): void
    {
        $this->regressaoPgv = app(PgvRegressaoService::class)->toggleEspuria($this->tenantId, (int) $amostraId);
        $this->dispatch('pgv-regressao-resultado', dados: $this->regressaoPgv);
    }

    // ---------- Cálculo das faces (233–235) ----------
    #[On('pgv-calcular-faces')]
    public function calcularFacesPgv(): void
    {
        $res = app(PgvFaceCalculoService::class)->recalcularTodas($this->tenantId);
        if (!$res['equacao']) {
            Notification::make()->title('Rode a regressão primeiro')->warning()->send();
            return;
        }
        Notification::make()->title($res['faces'] . ' face(s) calculada(s)!')->success()->send();
        $this->dispatch('pgv-mostrar-faces', dados: $this->facesGeoJson());
    }

    /** Faces com valor calculado (para colorir no mapa). */
    private function facesGeoJson(): array
    {
        $rows = DB::select("
            SELECT id, code, valor_m2_calculado::float AS valor, ST_AsGeoJSON(geo, 6) AS geo_json
            FROM face_quadras
            WHERE tenant_id = ? AND deleted_at IS NULL AND geo IS NOT NULL AND valor_m2_calculado IS NOT NULL
        ", [$this->tenantId]);

        return array_map(fn($r) => [
            'id'    => $r->id,
            'code'  => $r->code,
            'valor' => (float) $r->valor,
            'geo'   => json_decode($r->geo_json),
        ], $rows);
    }

    // ---------- Criar Amostra (225/226) por clique ----------
    #[On('pgv-clique-amostra')]
    public function abrirModalAmostra($lon, $lat): void
    {
        $this->pgvPontoLon = (string) $lon;
        $this->pgvPontoLat = (string) $lat;
        $this->mountAction('criarAmostraPgvAction');
    }

    public function criarAmostraPgvAction(): Action
    {
        return Action::make('criarAmostraPgvAction')
            ->modalHeading('Nova Amostra de Mercado')
            ->modalWidth('lg')
            ->form([
                \Filament\Forms\Components\TextInput::make('valor_m2')->label('Valor m² (mercado)')->numeric()->prefix('R$')->required(),
                \Filament\Forms\Components\TextInput::make('tipologia')->label('Tipologia'),
                \Filament\Forms\Components\Select::make('estado_conservacao')->label('Conservação')
                    ->options(['Bom' => 'Bom', 'Regular' => 'Regular', 'Ruim' => 'Ruim', 'Péssimo' => 'Péssimo']),
                \Filament\Forms\Components\TextInput::make('idade_aparente')->label('Idade Aparente (anos)')->numeric(),
            ])
            ->action(function (array $data) {
                PgvAmostra::create([
                    'tenant_id'          => $this->tenantId,
                    'valor_m2'           => $data['valor_m2'],
                    'tipologia'          => $data['tipologia'] ?? null,
                    'estado_conservacao' => $data['estado_conservacao'] ?? null,
                    'idade_aparente'     => $data['idade_aparente'] ?? null,
                    'geo'                => ['type' => 'Point', 'coordinates' => [(float) $this->pgvPontoLon, (float) $this->pgvPontoLat]],
                ]);
                Notification::make()->title('Amostra registrada!')->success()->send();
                $this->dispatch('pgv-amostra-criada');
            });
    }

    // ---------- Criar Pólo (227) por clique ----------
    #[On('pgv-clique-polo')]
    public function abrirModalPolo($lon, $lat): void
    {
        $this->pgvPontoLon = (string) $lon;
        $this->pgvPontoLat = (string) $lat;
        $this->mountAction('criarPoloPgvAction');
    }

    public function criarPoloPgvAction(): Action
    {
        return Action::make('criarPoloPgvAction')
            ->modalHeading('Novo Pólo Valorizante')
            ->modalWidth('sm')
            ->form([
                \Filament\Forms\Components\TextInput::make('name')->label('Nome do Pólo')->required(),
            ])
            ->action(function (array $data) {
                PgvPolo::create([
                    'tenant_id' => $this->tenantId,
                    'name'      => $data['name'],
                    'geo'       => ['type' => 'Point', 'coordinates' => [(float) $this->pgvPontoLon, (float) $this->pgvPontoLat]],
                ]);
                Notification::make()->title('Pólo criado!')->success()->send();
                $this->dispatch('pgv-polo-criado');
            });
    }

    // ---------- Criar Face de Quadra (233) por desenho ----------
    #[On('pgv-desenho-face')]
    public function abrirModalFace($geoJson): void
    {
        $this->pgvFaceGeoJson = json_encode($geoJson);
        $this->mountAction('criarFacePgvAction');
    }

    public function criarFacePgvAction(): Action
    {
        return Action::make('criarFacePgvAction')
            ->modalHeading('Nova Face de Quadra')
            ->modalWidth('lg')
            ->form([
                \Filament\Forms\Components\TextInput::make('code')->label('Código da Face/Seção'),
                \Filament\Forms\Components\Select::make('quadra_id')->label('Quadra')
                    ->options(fn() => \App\Models\Quadra::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->searchable()->required(),
                \Filament\Forms\Components\Select::make('logradouro_id')->label('Logradouro (confrontante)')
                    ->options(fn() => \App\Models\Logradouro::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->searchable(),
                \Filament\Forms\Components\Select::make('zona_id')->label('Zona')
                    ->options(fn() => \App\Models\Zona::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->action(function (array $data) {
                $geo = json_decode($this->pgvFaceGeoJson, true);
                $face = FaceQuadra::create([
                    'tenant_id'     => $this->tenantId,
                    'code'          => $data['code'] ?? null,
                    'quadra_id'     => $data['quadra_id'],
                    'logradouro_id' => $data['logradouro_id'] ?? null,
                    'zona_id'       => $data['zona_id'] ?? null,
                    'geo'           => $geo,
                ]);
                // cacheia extensão (ST_Length) igual às testadas/seções
                try {
                    DB::statement("UPDATE face_quadras SET extensao_geo = ST_Length(geo::geography) WHERE id = ?", [$face->id]);
                } catch (\Throwable $e) {
                }
                Notification::make()->title('Face de quadra criada!')->success()->send();
                $this->dispatch('limpar-rascunho-mapa');
                $this->dispatch('pgv-face-criada');
            });
    }

    // ---------- Simulação de IPTU (237–243) ----------
    public function simularIptuAction(): Action
    {
        return Action::make('simularIptuAction')
            ->modalHeading('Simulação de IPTU com a nova PGV')
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Simular')
            ->form([
                \Filament\Forms\Components\Grid::make(3)->schema([
                    \Filament\Forms\Components\TextInput::make('aliquota')->label('Alíquota (%)')->numeric()->default(1)->required(),
                    \Filament\Forms\Components\TextInput::make('percentual_valor_venal')->label('% do Valor Venal')->numeric()->default(100)->required(),
                    \Filament\Forms\Components\TextInput::make('limite_aumento')->label('Limitar aumento (%) — opcional')->numeric(),
                ]),
                \Filament\Forms\Components\Select::make('bairros')->label('Restringir por Bairro(s)')
                    ->options(fn() => \App\Models\Bairro::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->multiple(),
            ])
            ->action(function (array $data) {
                $this->simulacaoIptu = app(PgvSimulacaoIptuService::class)->simular($this->tenantId, [
                    'aliquota'               => (float) $data['aliquota'],
                    'percentual_valor_venal' => (float) $data['percentual_valor_venal'],
                    'limite_aumento'         => $data['limite_aumento'] ?? null,
                    'bairros'                => $data['bairros'] ?? [],
                ]);
                $t = $this->simulacaoIptu['totais'];
                Notification::make()
                    ->title('Simulação concluída')
                    ->body("IPTU atual R$ " . number_format($t['iptu_atual'], 2, ',', '.')
                        . " → simulado R$ " . number_format($t['iptu_simulado'], 2, ',', '.')
                        . " (" . ($t['variacao_pct'] ?? '—') . "%)")
                    ->success()->persistent()->send();
                $this->dispatch('pgv-simulacao-resultado', dados: $this->simulacaoIptu);
            });
    }
}
