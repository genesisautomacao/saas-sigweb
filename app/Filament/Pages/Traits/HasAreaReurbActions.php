<?php

namespace App\Filament\Pages\Traits;

use App\Models\AreaReurb;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

trait HasAreaReurbActions
{
    public ?int $areaReurbAtivaId = null;

    public function criarAreaReurbAction(): Action
    {
        return Action::make('criarAreaReurb')
            ->model(AreaReurb::class)
            ->modalHeading('Cadastrar Área de Regularização (REURB)')
            ->modalSubmitActionLabel('Salvar Área')
            ->modalWidth('3xl')
            ->form($this->getAreaReurbFormSchema())
            ->action(function (array $data) {
                $area = AreaReurb::create([
                    'tenant_id' => $this->tenantId,
                    'nome'      => $data['nome'],
                    'tipo_reurb' => $data['tipo_reurb'],
                    'status'    => $data['status'],
                    'observacao' => $data['observacao'] ?? null,
                    'geo'       => $this->geometriaRascunho,
                ]);

                try {
                    DB::statement(
                        "UPDATE areas_reurb SET area_geo = ST_Area(geo::geography) WHERE id = ?",
                        [$area->id]
                    );
                } catch (\Throwable) {
                }

                Notification::make()->title('Área REURB Criada!')->success()->send();

                $this->dispatch('adicionar-area_reurb-mapa', [
                    'id'   => $area->id,
                    'name' => $area->nome,
                    'geo'  => $this->geometriaRascunho,
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesAreaReurbAction(): Action
    {
        return Action::make('opcoesAreaReurb')
            ->hiddenLabel()
            ->modalHeading(function () {
                $area = AreaReurb::find($this->areaReurbAtivaId);
                return 'Área REURB' . ($area ? ' — ' . $area->nome : '');
            })
            ->modalDescription('Selecione a operação que deseja realizar:')
            ->modalWidth('sm')
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->form(function () {
                return [
                    \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('editar_ficha')
                            ->label('Ver / Editar Dados')
                            ->icon('heroicon-o-document-text')
                            ->color('primary')
                            ->action(fn() => $this->replaceMountedAction('editarAreaReurb')),
                    ])->fullWidth(),

                    \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('geometria')
                            ->label('Alterar Geometria (Editar Polígono)')
                            ->icon('heroicon-o-map')
                            ->color('warning')
                            ->action(function () {
                                $this->dispatch('iniciar-edicao-geometria-area-reurb', id: $this->areaReurbAtivaId);
                                $this->dispatch('fechar-modal-filament');
                            }),
                    ])->fullWidth(),

                    \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('excluir')
                            ->label('Excluir Área REURB')
                            ->icon('heroicon-o-trash')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->action(function () {
                                AreaReurb::where('id', $this->areaReurbAtivaId)->delete();
                                Notification::make()->title('Área REURB Excluída!')->success()->send();
                                $this->dispatch('remover-area_reurb-mapa', ['id' => $this->areaReurbAtivaId]);
                                $this->dispatch('fechar-modal-filament');
                            }),
                    ])->fullWidth(),
                ];
            });
    }

    public function editarAreaReurbAction(): Action
    {
        return Action::make('editarAreaReurb')
            ->model(AreaReurb::class)
            ->hiddenLabel()
            ->modalHeading(function () {
                $area = AreaReurb::find($this->areaReurbAtivaId);
                return 'Editar Área REURB' . ($area ? ' — ' . $area->nome : '');
            })
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $area = AreaReurb::find($this->areaReurbAtivaId);
                if (!$area) return [];

                return [
                    'nome'       => $area->nome,
                    'tipo_reurb' => $area->tipo_reurb,
                    'status'     => $area->status,
                    'observacao' => $area->observacao,
                ];
            })
            ->form($this->getAreaReurbFormSchema())
            ->action(function (array $data) {
                $area = AreaReurb::find($this->areaReurbAtivaId);
                if ($area) {
                    $area->update([
                        'nome'       => $data['nome'],
                        'tipo_reurb' => $data['tipo_reurb'],
                        'status'     => $data['status'],
                        'observacao' => $data['observacao'] ?? null,
                    ]);

                    Notification::make()->title('Área REURB Atualizada!')->success()->send();

                    $this->dispatch('atualizar-label-area_reurb', [
                        'id'   => $area->id,
                        'name' => $area->nome,
                    ]);
                }
            });
    }

    protected function getAreaReurbFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Section::make('Identificação')->schema([
                \Filament\Forms\Components\TextInput::make('nome')
                    ->label('Nome / Identificação da Área')
                    ->required()
                    ->maxLength(255),

                \Filament\Forms\Components\Select::make('tipo_reurb')
                    ->label('Tipo REURB')
                    ->options([
                        'Reurb-S' => 'Reurb-S — Social',
                        'Reurb-E' => 'Reurb-E — Específico',
                        'Sem Classificação' => 'Sem Classificação',
                    ])
                    ->default('Sem Classificação')
                    ->required(),

                \Filament\Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'em_analise'   => 'Em Análise',
                        'regularizado' => 'Regularizado',
                        'arquivado'    => 'Arquivado',
                    ])
                    ->default('em_analise')
                    ->required(),
            ])->columns(3),

            \Filament\Forms\Components\Textarea::make('observacao')
                ->label('Observação')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }
}
