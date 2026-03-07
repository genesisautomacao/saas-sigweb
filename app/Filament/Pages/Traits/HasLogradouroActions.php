<?php

namespace App\Filament\Pages\Traits;

use App\Models\Logradouro;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait HasLogradouroActions
{
    // Variável para guardar qual logradouro estamos editando
    public ?int $logradouroAtivoId = null;

    /**
     * Ação: Criar Novo Logradouro
     */
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
                $data['geo'] = $this->geometriaRascunho; // Pega a linha desenhada no mapa
                $data['code'] = (string) Str::uuid();

                $logradouro = Logradouro::create($data);

                Notification::make()->title('Logradouro Criado!')->success()->send();

                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');
                
                // Dispara um recarregamento da camada (o usuário pode precisar dar F5 ou podemos implementar via JS depois)
                $this->dispatch('atualizar-camada-logradouros');
            });
    }

    /**
     * Ação: Opções do Logradouro (Editar Nome, Geometria ou Excluir)
     */
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
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geometria')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        // Dispara o JS que habilita puxar os pontos da rua
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
                        $this->dispatch('atualizar-camada-logradouros');
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}