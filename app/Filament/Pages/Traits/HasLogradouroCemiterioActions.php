<?php

namespace App\Filament\Pages\Traits;

use App\Models\LogradouroCemiterio;
use App\Models\Cemiterio;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait HasLogradouroCemiterioActions
{
    public ?int $logradouroCemiterioAtivoId = null;
    // O $cemiterioPreSelecionadoId já existe na Trait da Quadra, então vamos apenas usá-lo!

    public function criarLogradouroCemiterioAction(): Action
    {
        return Action::make('criarLogradouroCemiterio')
            ->modalHeading('Cadastrar Caminho/Rua de Cemitério')
            ->modalSubmitActionLabel('Salvar Rua')
            ->modalWidth('xl')
            ->form([
                Select::make('cemiterio_id')
                    ->label('Pertence a qual Cemitério?')
                    ->options(fn() => Cemiterio::pluck('name', 'id'))
                    ->default(fn () => $this->cemiterioPreSelecionadoId) // Puxa do PostGIS
                    ->required(),
                TextInput::make('name')
                    ->label('Nome da Rua/Viela')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho; 
                $data['code'] = (string) Str::uuid();

                $logradouro = LogradouroCemiterio::create($data);
                Notification::make()->title('Rua do Cemitério Criada!')->success()->send();

                $this->dispatch('adicionar-logradouro_cemiterio-mapa', [
                    'id' => $logradouro->id,
                    'name' => $logradouro->name,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesLogradouroCemiterioAction(): Action
    {
        return Action::make('opcoesLogradouroCemiterio')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Rua de Cemitério #' . $this->logradouroCemiterioAtivoId)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $logradouro = LogradouroCemiterio::find($this->logradouroCemiterioAtivoId);
                return [
                    'cemiterio_id' => $logradouro ? $logradouro->cemiterio_id : null,
                    'name' => $logradouro ? $logradouro->name : null,
                ];
            })
            ->form([
                Select::make('cemiterio_id')
                    ->label('Cemitério')
                    ->options(fn() => Cemiterio::pluck('name', 'id'))
                    ->required(),
                TextInput::make('name')
                    ->label('Nome da Rua/Viela')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $logradouro = LogradouroCemiterio::find($this->logradouroCemiterioAtivoId);
                if ($logradouro) {
                    $logradouro->update($data);
                    Notification::make()->title('Rua Atualizada!')->success()->send();
                    $this->dispatch('atualizar-label-logradouro_cemiterio', ['id' => $logradouro->id, 'name' => $data['name']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geometria')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-logradouro_cemiterio', id: $this->logradouroCemiterioAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                    
                Action::make('excluir_logradouro_cemiterio')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        LogradouroCemiterio::where('id', $this->logradouroCemiterioAtivoId)->delete();
                        Notification::make()->title('Rua Excluída!')->success()->send();
                        $this->dispatch('remover-logradouro_cemiterio-mapa', ['id' => $this->logradouroCemiterioAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}