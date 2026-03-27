<?php

namespace App\Filament\Pages\Traits;

use App\Models\RuralHidrografia;
use App\Models\RuralLocalidade;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait HasRuralHidrografiaActions
{
    public ?int $ruralHidrografiaAtivaId = null;

    public function criarRuralHidrografiaAction(): Action
    {
        return Action::make('criarRuralHidrografia')
            ->modalHeading('Cadastrar Hidrografia')
            ->form([
                TextInput::make('nome')->label('Nome do Corpo d\'água')->required(),
                Select::make('tipo')
                    ->options(['Rio' => 'Rio', 'Córrego' => 'Córrego', 'Lago' => 'Lago', 'Açude' => 'Açude', 'Nascente' => 'Nascente'])
                    ->required(),
                Select::make('rural_localidade_id')
                    ->label('Localidade de Referência')
                    ->options(RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))
                    ->searchable(),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();

                $registro = RuralHidrografia::create($data);

                $this->dispatch('adicionar-rural_hidrografia-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->nome,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesRuralHidrografiaAction(): Action
    {
        return Action::make('opcoesRuralHidrografia')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Hidrografia: ' . RuralHidrografia::find($this->ruralHidrografiaAtivaId)?->nome)
            ->form([
                TextInput::make('nome')->required(),
                Select::make('tipo')->options(['Rio' => 'Rio', 'Córrego' => 'Córrego', 'Lago' => 'Lago', 'Açude' => 'Açude', 'Nascente' => 'Nascente'])->required(),
            ])
            ->fillForm(fn() => RuralHidrografia::find($this->ruralHidrografiaAtivaId)?->toArray())
            ->action(function (array $data) {
                RuralHidrografia::find($this->ruralHidrografiaAtivaId)->update($data);
                $this->dispatch('atualizar-label-rural_hidrografia', ['id' => $this->ruralHidrografiaAtivaId, 'name' => $data['nome']]);
            })
            ->extraModalFooterActions([
                Action::make('edit_geo')->label('Geometria')->color('warning')->action(fn() => $this->dispatch('iniciar-edicao-geometria-rural_hidrografia', id: $this->ruralHidrografiaAtivaId)),
                Action::make('del')->label('Excluir')->color('danger')->requiresConfirmation()->action(function() {
                    RuralHidrografia::where('id', $this->ruralHidrografiaAtivaId)->delete();
                    $this->dispatch('remover-rural_hidrografia-mapa', ['id' => $this->ruralHidrografiaAtivaId]);
                    $this->dispatch('fechar-modal-filament');
                })
            ]);
    }
}