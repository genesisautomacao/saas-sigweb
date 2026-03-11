<?php

namespace App\Filament\Pages\Traits;

use App\Models\Logradouro;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait HasLogradouroActions
{
    public ?int $logradouroAtivoId = null;

    public function criarLogradouroAction(): Action
    {
        return Action::make('criarLogradouro')
            ->modalHeading('Cadastrar Novo Logradouro')
            ->modalSubmitActionLabel('Salvar Logradouro')
            ->modalWidth('lg')
            ->form([
                TextInput::make('name')
                    ->label('Nome do Logradouro')
                    ->placeholder('Ex: Rua das Flores, Avenida Brasil...')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho; 
                $data['code'] = (string) Str::uuid();

                $logradouro = Logradouro::create($data);

                Notification::make()->title('Logradouro Criado!')->success()->send();

                // 🛑 Ação Cirúrgica de Adição
                $this->dispatch('adicionar-logradouro-mapa', [
                    'id' => $logradouro->id,
                    'name' => $logradouro->name,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesLogradouroAction(): Action
    {
        return Action::make('opcoesLogradouro')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Editar Logradouro #' . $this->logradouroAtivoId)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $logradouro = Logradouro::find($this->logradouroAtivoId);
                return [
                    'name' => $logradouro ? $logradouro->name : '',
                ];
            })
            ->form([
                TextInput::make('name')
                    ->label('Nome do Logradouro')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $logradouro = Logradouro::find($this->logradouroAtivoId);
                if ($logradouro) {
                    $logradouro->update($data);
                    Notification::make()->title('Nome Atualizado!')->success()->send();
                    
                    // 🛑 Ação Cirúrgica de Edição
                    $this->dispatch('atualizar-label-logradouro', [
                        'id' => $logradouro->id,
                        'name' => $data['name']
                    ]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geometria')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-logradouro', id: $this->logradouroAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                    
                Action::make('excluir_logradouro')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        Logradouro::where('id', $this->logradouroAtivoId)->delete();
                        Notification::make()->title('Logradouro Excluído!')->success()->send();
                        
                        // 🛑 Ação Cirúrgica de Exclusão
                        $this->dispatch('remover-logradouro-mapa', ['id' => $this->logradouroAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}