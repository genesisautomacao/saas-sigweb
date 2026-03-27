<?php

namespace App\Filament\Pages\Traits;

use App\Models\RuralEstrada;
use App\Models\RuralLocalidade;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait HasRuralEstradaActions
{
    public ?int $ruralEstradaAtivaId = null;

    public function criarRuralEstradaAction(): Action
    {
        return Action::make('criarRuralEstrada')
            ->modalHeading('Cadastrar Nova Estrada / Vicinal')
            ->form([
                TextInput::make('nome')->label('Nome da Estrada')->required(),
                Select::make('rural_localidade_id')
                    ->label('Localidade / Distrito')
                    ->options(RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))
                    ->searchable()->required(),
                Select::make('tipo_pavimento')
                    ->options(['Chão Batido' => 'Chão Batido', 'Cascalho' => 'Cascalho', 'Asfalto' => 'Asfalto', 'Poliédrico' => 'Poliédrico']),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();

                $registro = RuralEstrada::create($data);

                $this->dispatch('adicionar-rural_estrada-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->nome,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesRuralEstradaAction(): Action
    {
        return Action::make('opcoesRuralEstrada')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Estrada: ' . RuralEstrada::find($this->ruralEstradaAtivaId)?->nome)
            ->fillForm(function (): array {
                $reg = RuralEstrada::find($this->ruralEstradaAtivaId);
                return [
                    'nome' => $reg?->nome,
                    'rural_localidade_id' => $reg?->rural_localidade_id,
                    'tipo_pavimento' => $reg?->tipo_pavimento,
                ];
            })
            ->form([
                TextInput::make('nome')->required(),
                Select::make('rural_localidade_id')
                    ->options(RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))
                    ->required(),
                Select::make('tipo_pavimento')->options(['Chão Batido' => 'Chão Batido', 'Cascalho' => 'Cascalho', 'Asfalto' => 'Asfalto', 'Poliédrico' => 'Poliédrico']),
            ])
            ->action(function (array $data) {
                RuralEstrada::find($this->ruralEstradaAtivaId)->update($data);
                $this->dispatch('atualizar-label-rural_estrada', ['id' => $this->ruralEstradaAtivaId, 'name' => $data['nome']]);
            })
            ->extraModalFooterActions([
                Action::make('edit_geo')->label('Geometria')->color('warning')->action(fn() => $this->dispatch('iniciar-edicao-geometria-rural_estrada', id: $this->ruralEstradaAtivaId)),
                Action::make('del')->label('Excluir')->color('danger')->requiresConfirmation()->action(fn() => RuralEstrada::find($this->ruralEstradaAtivaId)->delete())
            ]);
    }
}