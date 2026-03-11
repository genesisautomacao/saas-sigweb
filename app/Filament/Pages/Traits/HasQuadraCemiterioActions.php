<?php

namespace App\Filament\Pages\Traits;

use App\Models\QuadraCemiterio;
use App\Models\Cemiterio;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HasQuadraCemiterioActions
{
    public ?int $quadraCemiterioAtivaId = null;
    public ?int $cemiterioPreSelecionadoId = null;

    public function criarQuadraCemiterioAction(): Action
    {
        return Action::make('criarQuadraCemiterio')
            ->modalHeading('Cadastrar Quadra de Cemitério')
            ->modalDescription('Polígono capturado. Defina os dados da quadra.')
            ->modalSubmitActionLabel('Salvar Quadra')
            ->modalWidth('xl')
            ->form([
                Select::make('cemiterio_id')
                    ->label('Pertence a qual Cemitério?')
                    ->options(fn() => Cemiterio::pluck('name', 'id'))
                    ->default(fn () => $this->cemiterioPreSelecionadoId) // 🛑 A MÁGICA: Puxa o ID detectado!
                    ->required(),
                TextInput::make('name')
                    ->label('Identificação (Ex: Quadra A, Setor 1)')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho; 
                $data['code'] = (string) Str::uuid();

                $quadra = QuadraCemiterio::create($data);
                DB::statement("UPDATE quadras_cemiterio SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$quadra->id]);
                Notification::make()->title('Quadra criada no mapa!')->success()->send();

                $this->dispatch('adicionar-quadra_cemiterio-mapa', [
                    'id' => $quadra->id,
                    'name' => $quadra->name,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesQuadraCemiterioAction(): Action
    {
        return Action::make('opcoesQuadraCemiterio')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Quadra de Cemitério #' . $this->quadraCemiterioAtivaId)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $quadra = QuadraCemiterio::find($this->quadraCemiterioAtivaId);
                return [
                    'cemiterio_id' => $quadra ? $quadra->cemiterio_id : null,
                    'name' => $quadra ? $quadra->name : null,
                ];
            })
            ->form([
                Select::make('cemiterio_id')
                    ->label('Pertence a qual Cemitério?')
                    ->options(fn() => Cemiterio::pluck('name', 'id'))
                    ->required(),
                TextInput::make('name')
                    ->label('Identificação')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $quadra = QuadraCemiterio::find($this->quadraCemiterioAtivaId);
                if ($quadra) {
                    $quadra->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-quadra_cemiterio', ['id' => $quadra->id, 'name' => $data['name']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geometria')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-quadra_cemiterio', id: $this->quadraCemiterioAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                    
                Action::make('excluir_quadra_cemiterio')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        QuadraCemiterio::where('id', $this->quadraCemiterioAtivaId)->delete();
                        Notification::make()->title('Quadra Excluída!')->success()->send();
                        $this->dispatch('remover-quadra_cemiterio-mapa', ['id' => $this->quadraCemiterioAtivaId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}