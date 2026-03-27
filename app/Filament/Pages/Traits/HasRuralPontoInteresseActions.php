<?php

namespace App\Filament\Pages\Traits;

use App\Models\RuralPontoInteresse;
use App\Models\RuralLocalidade;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait HasRuralPontoInteresseActions
{
    public ?int $ruralPontoInteresseAtivaId = null;

    public function criarRuralPontoInteresseAction(): Action
    {
        return Action::make('criarRuralPontoInteresse')
            ->modalHeading('Cadastrar Ponto de Interesse')
            ->form([
                TextInput::make('nome')->label('Nome do Local')->required(),
                Select::make('categoria')
                    ->options(['Educação' => 'Educação', 'Religião' => 'Religião', 'Saúde' => 'Saúde', 'Comércio' => 'Comércio', 'Lazer' => 'Lazer'])
                    ->required(),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();

                $registro = RuralPontoInteresse::create($data);

                $this->dispatch('adicionar-rural_ponto_interesse-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->nome,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesRuralPontoInteresseAction(): Action
    {
        return Action::make('opcoesRuralPontoInteresse')
            ->hiddenLabel()
            ->modalHeading(fn () => 'Ponto: ' . RuralPontoInteresse::find($this->ruralPontoInteresseAtivaId)?->nome)
            ->form([
                TextInput::make('nome')->required(),
                Select::make('categoria')->options(['Educação' => 'Educação', 'Religião' => 'Religião', 'Saúde' => 'Saúde', 'Comércio' => 'Comércio', 'Lazer' => 'Lazer'])->required(),
            ])
            ->fillForm(fn() => RuralPontoInteresse::find($this->ruralPontoInteresseAtivaId)?->toArray())
            ->action(fn(array $data) => RuralPontoInteresse::find($this->ruralPontoInteresseAtivaId)->update($data))
            ->extraModalFooterActions([
                Action::make('del')->label('Excluir')->color('danger')->requiresConfirmation()->action(function() {
                    RuralPontoInteresse::where('id', $this->ruralPontoInteresseAtivaId)->delete();
                    $this->dispatch('remover-rural_ponto_interesse-mapa', ['id' => $this->ruralPontoInteresseAtivaId]);
                    $this->dispatch('fechar-modal-filament');
                })
            ]);
    }
}