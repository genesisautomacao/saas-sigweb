<?php

namespace App\Filament\Pages\Traits;

use App\Models\PerimetroUrbano;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait HasPerimetroUrbanoActions
{
    public ?int $perimetroUrbanoAtivoId = null;

    public function criarPerimetroUrbanoAction(): Action
    {
        return Action::make('criarPerimetroUrbano')
            ->modalHeading('Cadastrar Novo Distrito / Limite')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Distrito')
            ->form([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                TextInput::make('distrito')
                    ->label('Classificação (opcional)')
                    ->helperText('Ex: Distrito, Limite Municipal, Perímetro Urbano...')
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo']       = $this->geometriaRascunho;
                $data['code']      = (string) Str::uuid();

                $registro = PerimetroUrbano::create($data);

                Notification::make()->title('Distrito / Limite criado!')->success()->send();

                $this->dispatch('adicionar-perimetro-urbano-mapa', [
                    'id'   => $registro->id,
                    'name' => $registro->name,
                    'geo'  => $this->geometriaRascunho,
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesPerimetroUrbanoAction(): Action
    {
        return Action::make('opcoesPerimetroUrbano')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Editar Distrito: ' . PerimetroUrbano::find($this->perimetroUrbanoAtivoId)?->name)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $reg = PerimetroUrbano::find($this->perimetroUrbanoAtivoId);
                return [
                    'name'     => $reg?->name,
                    'distrito' => $reg?->distrito,
                ];
            })
            ->form([
                TextInput::make('name')->label('Nome')->required()->maxLength(255),
                TextInput::make('distrito')->label('Classificação (opcional)')->maxLength(255),
            ])
            ->action(function (array $data) {
                $reg = PerimetroUrbano::find($this->perimetroUrbanoAtivoId);
                if ($reg) {
                    $reg->update($data);
                    Notification::make()->title('Dados atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-perimetro-urbano', ['id' => $reg->id, 'name' => $data['name']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_perimetro_urbano')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-perimetro_urbano', id: $this->perimetroUrbanoAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_perimetro_urbano')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        PerimetroUrbano::where('id', $this->perimetroUrbanoAtivoId)->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-perimetro-urbano-mapa', ['id' => $this->perimetroUrbanoAtivoId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}
