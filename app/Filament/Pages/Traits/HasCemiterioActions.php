<?php

namespace App\Filament\Pages\Traits;

use App\Models\Cemiterio;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HasCemiterioActions
{
    public function criarCemiterioAction(): Action
    {
        return Action::make('criarCemiterio')
            ->modalHeading('Cadastrar Novo Cemitério')
            ->modalDescription('O polígono foi capturado. Defina o nome e o endereço deste cemitério.')
            ->modalSubmitActionLabel('Salvar Cemitério')
            ->modalWidth('xl')
            ->form([
                TextInput::make('name')->label('Nome do Cemitério')->required()->maxLength(255),
                TextInput::make('address')->label('Endereço / Referência')->maxLength(255),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho; 
                $data['code'] = (string) Str::uuid();

                $cemiterio = Cemiterio::create($data);
                DB::statement("UPDATE cemiterios SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$cemiterio->id]);
                Notification::make()->title('Cemitério criado no mapa!')->success()->send();

                // 🛑 MÁGICA 1: Injeta só o cemitério novo no mapa!
                $this->dispatch('adicionar-cemiterio-mapa', [
                    'id' => $cemiterio->id,
                    'name' => $cemiterio->name,
                    'geo' => $this->geometriaRascunho
                ]);

                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesCemiterioAction(): Action
    {
        return Action::make('opcoesCemiterio')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Cemitério #' . $this->cemiterioAtivoId)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $cemiterio = Cemiterio::find($this->cemiterioAtivoId);
                return [
                    'name' => $cemiterio ? $cemiterio->name : null,
                    'address' => $cemiterio ? $cemiterio->address : null,
                ];
            })
            ->form([
                TextInput::make('name')->label('Nome do Cemitério')->required()->maxLength(255),
                TextInput::make('address')->label('Endereço / Referência')->maxLength(255),
            ])
            ->action(function (array $data) {
                $cemiterio = Cemiterio::find($this->cemiterioAtivoId);
                if ($cemiterio) {
                    $cemiterio->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    
                    // 🛑 MÁGICA 2: Troca só o nome em cima do polígono
                    $this->dispatch('atualizar-label-cemiterio', ['id' => $cemiterio->id, 'name' => $data['name']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geometria')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-cemiterio', id: $this->cemiterioAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                    
                Action::make('excluir_cemiterio')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        Cemiterio::where('id', $this->cemiterioAtivoId)->delete();
                        Notification::make()->title('Cemitério Excluído!')->success()->send();
                        
                        // 🛑 MÁGICA 3: Apaga só o polígono da memória
                        $this->dispatch('remover-cemiterio-mapa', ['id' => $this->cemiterioAtivoId]);
                    }),
            ]);
    }
}