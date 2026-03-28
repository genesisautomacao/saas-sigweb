<?php

namespace App\Filament\Pages\Traits;

use App\Models\Bairro;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

trait HasBairroActions
{
    public ?int $bairroAtivoId = null;

    public function criarBairroAction(): Action
    {
        return Action::make('criarBairro')
            ->modalHeading('Cadastrar Novo Bairro')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Bairro')
            ->form([
                TextInput::make('name')
                    ->label('Nome do Bairro')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho; 
                $data['code'] = (string) Str::uuid();

                $registro = Bairro::create($data);
                
                // Atualiza a área automaticamente no PostGIS (envolto em try-catch caso a coluna area_geo não exista na sua model de Bairros)
                try {
                    DB::statement("UPDATE bairros SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$registro->id]);
                } catch (\Exception $e) {}

                Notification::make()->title('Bairro Criado!')->success()->send();

                $this->dispatch('adicionar-bairro-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->name,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

   public function opcoesBairroAction(): Action
    {
        return Action::make('opcoesBairro')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Editar Bairro: ' . Bairro::find($this->bairroAtivoId)?->name)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $reg = Bairro::find($this->bairroAtivoId);
                return [
                    'name' => $reg?->name,
                ];
            })
            ->form([
                TextInput::make('name')->label('Nome do Bairro')->required()->maxLength(255),
            ])
            ->action(function (array $data) {
                $reg = Bairro::find($this->bairroAtivoId);
                if ($reg) {
                    $reg->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-bairro', ['id' => $reg->id, 'name' => $data['name']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_bairro')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        // O Gatilho para o Javascript habilitar os nós do polígono!
                        $this->dispatch('iniciar-edicao-geometria-bairro', id: $this->bairroAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_bairro')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        Bairro::where('id', $this->bairroAtivoId)->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-bairro-mapa', ['id' => $this->bairroAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}