<?php

namespace App\Filament\Pages\Traits;

use App\Models\Bairro;
use App\Models\Loteamento;
use App\Models\Quadra;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

trait HasQuadraActions
{
    public ?int $quadraAtivaId = null;

    // Variáveis que receberão o auto-preenchimento topológico
    public ?int $quadraBairroPreSelecionadoId = null;
    public ?int $quadraLoteamentoPreSelecionadoId = null;
    public ?int $quadraPerimetroPreSelecionadoId = null;

    // Pré-cálculo de área exibido no modal de criação (preenchido em interceptarDesenho)
    public ?float $quadraAreaCalculada = null;

    public function criarQuadraAction(): Action
    {
        return Action::make('criarQuadra')
            ->modalHeading('Cadastrar Nova Quadra')
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Salvar Quadra')
            ->form([
                Placeholder::make('area_calculada')
                    ->label('Área calculada')
                    ->content(fn(): HtmlString => new HtmlString(
                        $this->quadraAreaCalculada !== null
                            ? '<strong style="font-size:14px;color:#0369a1;">' . number_format($this->quadraAreaCalculada, 2, ',', '.') . ' m²</strong>'
                            : '<em style="color:#9ca3af;">Sem geometria — desenhe a área no mapa primeiro.</em>'
                    )),

                TextInput::make('name')
                    ->label('Identificação da Quadra (Ex: A, 10, etc)')
                    ->required()
                    ->maxLength(255),
                // Refatoração PoC Tangará: setor_codigo saiu (redundante) — o código
                // municipal da quadra (item 45) é o campo `codigo`.
                TextInput::make('codigo')
                    ->label('Código da Quadra')
                    ->maxLength(50)
                    ->nullable(),
                Select::make('bairro_id')
                    ->label('Bairro')
                    ->options(Bairro::where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->default(fn() => $this->quadraBairroPreSelecionadoId)
                    ->searchable(),
                Select::make('loteamento_id')
                    ->label('Loteamento')
                    ->options(Loteamento::where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->default(fn() => $this->quadraLoteamentoPreSelecionadoId)
                    ->searchable(),
                // Item 75 — campos customizados do município (quadra)
                ...\App\Services\Coleta\CampoCustomizadoService::componentes('quadra'),
            ])
            ->action(function (array $data) {
                // 🛑 VALIDAÇÃO ANTIFRAUDE: O usuário mudou o Select manualmente?
                $polyWKT = "ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($this->geometriaRascunho) . "'), 4326)";
                $bairroId = $data['bairro_id'] ?? null;
                $loteamentoId = $data['loteamento_id'] ?? null;

                if (!$bairroId && !$loteamentoId) {
                    Notification::make()->title('Erro Obrigatório')->body('A quadra deve pertencer a pelo menos um Bairro ou Loteamento.')->danger()->send();
                    throw new \Filament\Support\Exceptions\Halt();
                }

                if ($bairroId) {
                    $valBairro = DB::selectOne("SELECT ST_Area(ST_Difference($polyWKT, (SELECT geo::geometry FROM bairros WHERE id = ?))::geography) as area_fora", [$bairroId]);
                    if ($valBairro && $valBairro->area_fora > 1.0) {
                        Notification::make()->title('Incompatibilidade Espacial')->body('A quadra desenhada possui áreas que vazam para fora do Bairro selecionado.')->danger()->send();
                        throw new \Filament\Support\Exceptions\Halt();
                    }
                }

                if ($loteamentoId) {
                    $valLoteamento = DB::selectOne("SELECT ST_Area(ST_Difference($polyWKT, (SELECT geo::geometry FROM loteamentos WHERE id = ?))::geography) as area_fora", [$loteamentoId]);
                    if ($valLoteamento && $valLoteamento->area_fora > 1.0) {
                        Notification::make()->title('Incompatibilidade Espacial')->body('A quadra desenhada possui áreas que vazam para fora do Loteamento selecionado.')->danger()->send();
                        throw new \Filament\Support\Exceptions\Halt();
                    }
                }

                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();
                // Atribui o perímetro de forma invisível para o usuário
                $data['perimetro_id'] = $this->quadraPerimetroPreSelecionadoId;
                $data['area_geo'] = $this->quadraAreaCalculada;

                $registro = Quadra::create($data);

                try {
                    DB::statement("UPDATE quadras SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$registro->id]);
                } catch (\Exception $e) {
                }

                Notification::make()->title('Quadra Criada!')->success()->send();

                $this->dispatch('adicionar-quadra-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->name,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');

                // Limpa as variáveis de pré-detecção pra próxima criação não herdar dados
                $this->quadraBairroPreSelecionadoId = null;
                $this->quadraLoteamentoPreSelecionadoId = null;
                $this->quadraPerimetroPreSelecionadoId = null;
                $this->quadraAreaCalculada = null;
            });
    }

    public function opcoesQuadraAction(): Action
    {
        return Action::make('opcoesQuadra')
            ->hiddenLabel()
            ->modalHeading(fn() => 'Editar Quadra: ' . Quadra::find($this->quadraAtivaId)?->name)
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $reg = Quadra::find($this->quadraAtivaId);
                return [
                    'name'         => $reg?->name,
                    'codigo'       => $reg?->codigo,
                    'bairro_id'    => $reg?->bairro_id,
                    'loteamento_id' => $reg?->loteamento_id,
                    'dados_customizados' => $reg?->dados_customizados ?? [],
                ];
            })
            ->form([
                Placeholder::make('area_atual')
                    ->label('Área atual')
                    ->content(function (): HtmlString {
                        $reg = Quadra::find($this->quadraAtivaId);
                        $valor = $reg?->area_geo;
                        return new HtmlString(
                            $valor !== null
                                ? '<strong style="font-size:14px;color:#0369a1;">' . number_format((float) $valor, 2, ',', '.') . ' m²</strong>'
                                : '<em style="color:#9ca3af;">Sem geometria registrada.</em>'
                        );
                    }),

                TextInput::make('name')->label('Identificação da Quadra')->required()->maxLength(255),
                TextInput::make('codigo')->label('Código da Quadra')->maxLength(50)->nullable(),
                Select::make('bairro_id')
                    ->label('Bairro')
                    ->options(Bairro::where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->helperText('Atualizado automaticamente ao mover a quadra no mapa.')
                    ->searchable(),
                Select::make('loteamento_id')
                    ->label('Loteamento')
                    ->options(Loteamento::where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->helperText('Atualizado automaticamente ao mover a quadra no mapa.')
                    ->searchable(),

                // Item 75 — campos customizados do município (quadra)
                ...\App\Services\Coleta\CampoCustomizadoService::componentes('quadra'),

                // Faces de Quadra (PGV) — a face é FILHA da quadra: lista aqui, com
                // visualização individual (👁) e criação pelo botão "Nova Face" no rodapé.
                \Filament\Forms\Components\Section::make('Faces de Quadra')
                    ->collapsible()
                    ->collapsed(fn() => \App\Models\FaceQuadra::query()->where('quadra_id', $this->quadraAtivaId)->doesntExist())
                    ->visible(fn() => in_array('pgv', \Filament\Facades\Filament::getTenant()?->modules ?? []))
                    ->schema([
                        Placeholder::make('faces_da_quadra')
                            ->hiddenLabel()
                            ->content(function (): HtmlString {
                                $faces = \App\Models\FaceQuadra::query()
                                    ->with('logradouro:id,name')
                                    ->where('quadra_id', $this->quadraAtivaId)
                                    ->orderBy('code')
                                    ->get();

                                if ($faces->isEmpty()) {
                                    return new HtmlString(
                                        '<p style="color:#9ca3af;font-size:13px;margin:4px 0;">Nenhuma face cadastrada. Use o botão <b>Nova Face</b> abaixo — o desenho gruda no contorno desta quadra.</p>'
                                    );
                                }

                                $html = '<div style="overflow-x:auto;"><table style="width:100%;font-size:13px;border-collapse:collapse;">'
                                    . '<thead><tr style="border-bottom:1px solid #e5e7eb;">'
                                    . '<th style="text-align:center;padding:4px 8px;font-weight:600;color:#6b7280;" title="Exibir no mapa">Mapa</th>'
                                    . '<th style="text-align:left;padding:4px 8px;font-weight:600;color:#6b7280;">Código</th>'
                                    . '<th style="text-align:left;padding:4px 8px;font-weight:600;color:#6b7280;">Logradouro</th>'
                                    . '<th style="text-align:right;padding:4px 8px;font-weight:600;color:#6b7280;">Extensão</th>'
                                    . '<th style="text-align:right;padding:4px 8px;font-weight:600;color:#6b7280;">Valor m²</th>'
                                    . '</tr></thead><tbody>';

                                foreach ($faces as $face) {
                                    $codigo = htmlspecialchars($face->code ?: ('Face #' . $face->sequential_id), ENT_QUOTES, 'UTF-8');
                                    $logradouro = htmlspecialchars($face->logradouro?->name ?? '—', ENT_QUOTES, 'UTF-8');
                                    $ext = $face->extensao_geo !== null ? number_format((float) $face->extensao_geo, 1, ',', '.') . ' m' : '—';
                                    $valor = $face->valor_m2_calculado !== null ? 'R$ ' . number_format((float) $face->valor_m2_calculado, 2, ',', '.') : '—';
                                    // Estado no servidor (facesQuadraVisiveis): o check sobrevive a fechar/reabrir o modal
                                    $checked = in_array($face->id, $this->facesQuadraVisiveis, true) ? 'checked' : '';

                                    $html .= '<tr style="border-bottom:1px solid #f3f4f6;">'
                                        . '<td style="padding:4px 8px;text-align:center;">'
                                        . '<input type="checkbox" ' . $checked . ' '
                                        . 'onchange="Livewire.dispatch(\'toggle-face-quadra\', { faceId: ' . $face->id . ' })" '
                                        . 'style="width:16px;height:16px;accent-color:#db2777;cursor:pointer;vertical-align:middle;" '
                                        . 'title="Exibir esta face no mapa" />'
                                        . '</td>'
                                        . '<td style="padding:4px 8px;font-weight:600;">' . $codigo . '</td>'
                                        . '<td style="padding:4px 8px;">' . $logradouro . '</td>'
                                        . '<td style="padding:4px 8px;text-align:right;">' . $ext . '</td>'
                                        . '<td style="padding:4px 8px;text-align:right;">' . $valor . '</td>'
                                        . '</tr>';
                                }

                                return new HtmlString($html . '</tbody></table></div>');
                            }),
                    ]),
            ])
            ->action(function (array $data) {
                $reg = Quadra::find($this->quadraAtivaId);
                if ($reg) {
                    $bairroId = $data['bairro_id'] ?? null;
                    $loteamentoId = $data['loteamento_id'] ?? null;

                    if (!$bairroId && !$loteamentoId) {
                        Notification::make()->title('Erro Obrigatório')->body('A quadra deve pertencer a pelo menos um Bairro ou Loteamento.')->danger()->send();
                        throw new \Filament\Support\Exceptions\Halt();
                    }

                    // 🛑 As travas espaciais foram removidas daqui para permitir o "Override" manual pelo gestor.

                    $reg->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-quadra', ['id' => $reg->id, 'name' => $data['name']]);
                }
            })
            ->extraModalFooterActions([
                // Face de quadra nasce DAQUI (não mais em Ferramentas): fecha o modal e
                // inicia o desenho com o ímã grudado no contorno DESTA quadra.
                Action::make('nova_face_quadra')
                    ->label('Nova Face')
                    ->color('info')
                    ->icon('heroicon-o-view-columns')
                    ->visible(fn() => in_array('pgv', \Filament\Facades\Filament::getTenant()?->modules ?? [])
                        && (auth()->user()?->can('gerenciar_face_quadras') ?? false))
                    ->action(function () {
                        $this->dispatch('iniciar-desenho-face-quadra', quadraId: $this->quadraAtivaId);
                        $this->dispatch('fechar-modal-filament');

                        Notification::make()
                            ->title('Desenhe a face da quadra')
                            ->body('O traço gruda no contorno da quadra. Dê dois cliques para finalizar.')
                            ->info()
                            ->send();
                    }),

                Action::make('editar_geo_quadra')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-quadra', id: $this->quadraAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('imprimir_planta_quadra')
                    ->label('Planta da Quadra')
                    ->color('success')
                    ->icon('heroicon-o-document-text')
                    ->action(function () {
                        // Fecha a modal e dispara a captura do mapa via JS.
                        // O JS encerra chamando $this->imprimirPlantaQuadra($id, $base64).
                        $this->dispatch('capturar-mapa-planta-quadra', id: $this->quadraAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_quadra')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        Quadra::find($this->quadraAtivaId)?->delete();
                        Notification::make()->title('Excluída!')->success()->send();
                        $this->dispatch('remover-quadra-mapa', ['id' => $this->quadraAtivaId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    /**
     * Recebe o base64 do canvas capturado e gera o PDF da Planta da Quadra.
     * (TR Tangará Intranet #16)
     */
    public function imprimirPlantaQuadra($quadraId, $mapImageBase64)
    {
        $quadra = Quadra::query()->find($quadraId);
        if (!$quadra) {
            Notification::make()->title('Erro')->body('Quadra não encontrada.')->danger()->send();
            return;
        }

        $service = app(\App\Services\Gis\PlantaQuadraPdfService::class);
        return $service->generatePdf($quadraId, $mapImageBase64);
    }
}
