<?php

namespace App\Filament\Pages\Traits;

use App\Models\RuralPonte;
use App\Models\RuralLocalidade;
use App\Models\RuralEstrada;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait HasRuralPonteActions
{
    public ?int $ruralPonteAtivaId = null;

    public function criarRuralPonteAction(): Action
    {
        return Action::make('criarRuralPonte')
            ->modalHeading('Cadastrar Ponte Rural')
            ->form([
                TextInput::make('nome_referencia')->label('Nome/Referência')->required(),
                Select::make('material_construcao')
                    ->options(['Madeira' => 'Madeira', 'Concreto' => 'Concreto', 'Metálica' => 'Metálica', 'Mista' => 'Mista']),
                Select::make('rural_estrada_id')
                    ->label('Estrada Pertencente')
                    ->options(RuralEstrada::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))
                    ->searchable(),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();

                $registro = RuralPonte::create($data);

                $this->dispatch('adicionar-rural_ponte-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->nome_referencia,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesRuralPonteAction(): Action
    {
        return Action::make('opcoesRuralPonte')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Ponte: ' . RuralPonte::find($this->ruralPonteAtivaId)?->nome_referencia)
            ->form([
                TextInput::make('nome_referencia')->required(),
                Select::make('material_construcao')->options(['Madeira' => 'Madeira', 'Concreto' => 'Concreto', 'Metálica' => 'Metálica', 'Mista' => 'Mista']),
            ])
            ->fillForm(fn() => RuralPonte::find($this->ruralPonteAtivaId)?->toArray())
            ->action(fn(array $data) => RuralPonte::find($this->ruralPonteAtivaId)->update($data))
            ->extraModalFooterActions([
                Action::make('del')->label('Excluir')->color('danger')->requiresConfirmation()->action(function() {
                    RuralPonte::where('id', $this->ruralPonteAtivaId)->delete();
                    $this->dispatch('remover-rural_ponte-mapa', ['id' => $this->ruralPonteAtivaId]);
                    $this->dispatch('fechar-modal-filament');
                })
            ]);
    }
}