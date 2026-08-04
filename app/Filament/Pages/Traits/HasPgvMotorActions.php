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
    // A face nasce DO MODAL DA QUADRA (relação pai→filho, como logradouro→seções);
    // o contexto abaixo pré-seleciona a quadra de origem no formulário.
    public ?int $pgvFaceQuadraContextoId = null;

    // Auto-detecção topológica ao abrir o modal (ambos editáveis no formulário)
    public ?int $pgvFaceLogradouroPreId = null;

    public ?int $pgvFaceZonaPreId = null;

    #[On('pgv-desenho-face')]
    public function abrirModalFace($geoJson, $quadraId = null): void
    {
        $this->pgvFaceGeoJson = json_encode($geoJson);
        $this->pgvFaceQuadraContextoId = $quadraId ? (int) $quadraId : null;

        $lineWKT = "ST_SetSRID(ST_GeomFromGeoJSON('".json_encode($geoJson)."'), 4326)";

        // Logradouro confrontante por PREDOMINÂNCIA: vence o que acompanha o MAIOR
        // comprimento da face (trecho do traço dentro da faixa de 20 m de cada rua) —
        // o KNN puro pegava a rua que só encosta na ponta da quadra.
        $logradouro = DB::selectOne(
            "SELECT lg.id,
                    ST_Length(ST_Intersection(
                        {$lineWKT}::geometry,
                        ST_Buffer(lg.geo::geography, 20)::geometry
                    )::geography) AS comprimento
             FROM logradouros lg
             WHERE lg.tenant_id = ? AND lg.geo IS NOT NULL AND lg.deleted_at IS NULL
               AND ST_DWithin({$lineWKT}::geography, lg.geo::geography, 20)
             ORDER BY comprimento DESC
             LIMIT 1",
            [$this->tenantId]
        );

        // Fallback: nenhuma rua a 20 m da face → a mais próxima (KNN)
        if (! $logradouro) {
            $logradouro = DB::selectOne(
                "SELECT id FROM logradouros
                 WHERE tenant_id = ? AND geo IS NOT NULL AND deleted_at IS NULL
                 ORDER BY geo::geometry <-> {$lineWKT}::geometry
                 LIMIT 1",
                [$this->tenantId]
            );
        }
        $this->pgvFaceLogradouroPreId = $logradouro?->id;

        // Zona: quadra em DUAS zonas → vence a que contém o MAIOR comprimento da face
        // (ST_Length da interseção do traço com cada zona, decrescente).
        $zona = DB::selectOne(
            "SELECT z.id,
                    ST_Length(ST_Intersection({$lineWKT}::geometry, z.geo::geometry)::geography) AS comprimento
             FROM zonas z
             WHERE z.tenant_id = ? AND z.geo IS NOT NULL AND z.deleted_at IS NULL
               AND ST_Intersects({$lineWKT}::geometry, z.geo::geometry)
             ORDER BY comprimento DESC
             LIMIT 1",
            [$this->tenantId]
        );

        // Fallback: traço fora de qualquer zona (ex.: face na divisa) → zona mais próxima
        if (! $zona) {
            $zona = DB::selectOne(
                "SELECT id FROM zonas
                 WHERE tenant_id = ? AND geo IS NOT NULL AND deleted_at IS NULL
                 ORDER BY geo::geometry <-> {$lineWKT}::geometry
                 LIMIT 1",
                [$this->tenantId]
            );
        }
        $this->pgvFaceZonaPreId = $zona?->id;

        $this->mountAction('criarFacePgvAction');
    }

    /**
     * Faces atualmente exibidas no mapa (checkbox da lista no modal da quadra).
     * Estado no SERVIDOR: o check fica correto mesmo fechando/reabrindo o modal.
     */
    public array $facesQuadraVisiveis = [];

    public ?int $faceQuadraAtivaId = null;

    // ---------- Modal de opções da face (clicar na face no mapa) ----------
    #[On('abrirOpcoesFaceQuadra')]
    public function abrirOpcoesFaceQuadra($id): void
    {
        $this->faceQuadraAtivaId = (int) $id;
        $this->mountAction('opcoesFaceQuadra');
    }

    public function opcoesFaceQuadraAction(): Action
    {
        return Action::make('opcoesFaceQuadra')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Editar Face: '.(FaceQuadra::find($this->faceQuadraAtivaId)?->code ?: ('Face #'.FaceQuadra::find($this->faceQuadraAtivaId)?->sequential_id)))
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $face = FaceQuadra::find($this->faceQuadraAtivaId);

                return [
                    'code' => $face?->code,
                    'quadra_id' => $face?->quadra_id,
                    'logradouro_id' => $face?->logradouro_id,
                    'zona_id' => $face?->zona_id,
                ];
            })
            ->form([
                \Filament\Forms\Components\Placeholder::make('resumo_face')
                    ->hiddenLabel()
                    ->content(function (): \Illuminate\Support\HtmlString {
                        $face = FaceQuadra::find($this->faceQuadraAtivaId);
                        $ext = $face?->extensao_geo !== null ? number_format((float) $face->extensao_geo, 1, ',', '.').' m' : '—';
                        $valor = $face?->valor_m2_calculado !== null ? 'R$ '.number_format((float) $face->valor_m2_calculado, 2, ',', '.').'/m²' : 'não calculado';

                        return new \Illuminate\Support\HtmlString(
                            '<div style="display:flex;gap:18px;font-size:13px;color:#374151;">'
                            .'<span><b>Extensão:</b> '.$ext.'</span>'
                            .'<span><b>Valor PGV:</b> '.$valor.'</span>'
                            .'</div>'
                        );
                    }),
                \Filament\Forms\Components\TextInput::make('code')->label('Código da Face/Seção'),
                \Filament\Forms\Components\Select::make('quadra_id')->label('Quadra')
                    ->options(fn () => \App\Models\Quadra::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->searchable()->required(),
                \Filament\Forms\Components\Select::make('logradouro_id')->label('Logradouro (confrontante)')
                    ->options(fn () => \App\Models\Logradouro::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->searchable(),
                \Filament\Forms\Components\Select::make('zona_id')->label('Zona')
                    ->options(fn () => \App\Models\Zona::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->action(function (array $data) {
                $face = FaceQuadra::find($this->faceQuadraAtivaId);

                if ($face) {
                    $face->update($data);
                    Notification::make()->title('Face atualizada!')->success()->send();
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_face_quadra')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-face_quadra', id: $this->faceQuadraAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_face_quadra')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        $faceId = $this->faceQuadraAtivaId;
                        FaceQuadra::find($faceId)?->delete();

                        // Tira do mapa e do estado dos checkboxes
                        $this->facesQuadraVisiveis = array_values(array_diff($this->facesQuadraVisiveis, [$faceId]));
                        $this->dispatch('toggle-face-quadra-mapa', id: $faceId, visivel: false);
                        $this->dispatch('remover-face-pgv-mapa', id: $faceId);

                        Notification::make()->title('Face excluída!')->success()->send();
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    /** Salva a geometria editada da face (fluxo "Geometria" do modal). */
    #[On('salvarNovaGeometriaFaceQuadra')]
    public function salvarNovaGeometriaFaceQuadra($id, $geoJson): void
    {
        $face = FaceQuadra::query()->find($id);

        if (! $face) {
            return;
        }

        $face->geo = $geoJson;
        $face->save();

        try {
            DB::statement('UPDATE face_quadras SET extensao_geo = ST_Length(geo::geography) WHERE id = ?', [$face->id]);
        } catch (\Throwable $e) {
        }

        Notification::make()->title('Geometria da face atualizada!')->success()->send();
    }

    #[On('toggle-face-quadra')]
    public function toggleFaceQuadra($faceId): void
    {
        $face = FaceQuadra::query()->find($faceId);

        if (! $face) {
            return;
        }

        $visivel = ! in_array($face->id, $this->facesQuadraVisiveis, true);
        $this->facesQuadraVisiveis = $visivel
            ? array_values(array_merge($this->facesQuadraVisiveis, [$face->id]))
            : array_values(array_diff($this->facesQuadraVisiveis, [$face->id]));

        $rotulo = trim(($face->code ?: 'Face #'.$face->sequential_id)
            .($face->valor_m2_calculado ? ' — R$ '.number_format((float) $face->valor_m2_calculado, 2, ',', '.').'/m²' : ''));

        $this->dispatch('toggle-face-quadra-mapa', id: $face->id, geo: $face->geo_json, rotulo: $rotulo, visivel: $visivel);
    }

    public function criarFacePgvAction(): Action
    {
        return Action::make('criarFacePgvAction')
            ->modalHeading('Nova Face de Quadra')
            ->modalWidth('lg')
            ->fillForm(fn (): array => [
                'quadra_id' => $this->pgvFaceQuadraContextoId,
                'logradouro_id' => $this->pgvFaceLogradouroPreId,
                'zona_id' => $this->pgvFaceZonaPreId,
            ])
            ->form([
                \Filament\Forms\Components\TextInput::make('code')->label('Código da Face/Seção'),
                \Filament\Forms\Components\Select::make('quadra_id')->label('Quadra')
                    ->options(fn() => \App\Models\Quadra::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->helperText('Pré-selecionada quando o desenho parte do modal da quadra.')
                    ->searchable()->required(),
                \Filament\Forms\Components\Select::make('logradouro_id')->label('Logradouro (confrontante)')
                    ->options(fn() => \App\Models\Logradouro::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->helperText('Detectado como o mais próximo do traço. Pode trocar.')
                    ->searchable(),
                \Filament\Forms\Components\Select::make('zona_id')->label('Zona')
                    ->options(fn() => \App\Models\Zona::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->helperText('Quadra em duas zonas: vence a que contém o maior trecho da face. Pode trocar.')
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

                // Exibe a face recém-criada no mapa imediatamente
                $this->toggleFaceQuadra($face->id);
                $this->pgvFaceQuadraContextoId = null;
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
