<?php

namespace App\Filament\Pages\Traits;

use App\Models\RuralPontoInteresse;
use App\Models\RuralLocalidade;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait HasRuralPontoInteresseActions
{
    public ?int $ruralPontoInteresseAtivaId = null;

    public function criarRuralPontoInteresseAction(): Action
    {
        return Action::make('criarRuralPontoInteresse')
            ->modalHeading('Cadastrar Ponto de Interesse')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Ponto')
            ->form([
                TextInput::make('nome')->label('Nome do Local')->required()->maxLength(255),
                Select::make('categoria')
                    ->label('Categoria')
                    ->options([
                        'Escola' => 'Escola / Educação',
                        'Saúde' => 'Posto de Saúde',
                        'Igreja' => 'Igreja / Templo',
                        'Turismo' => 'Ponto Turístico / Lazer',
                        'Comércio' => 'Comércio Local',
                        'Outro' => 'Outro'
                    ])
                    ->required(),
                Select::make('rural_localidade_id')
                    ->label('Localidade / Distrito Base')
                    ->options(RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))
                    ->default(fn() => $this->ruralLocalidadePreSelecionadaId) // 🛑 Injeção inteligente
                    ->searchable()
                    ->required(),
                Textarea::make('observacoes')
                    ->label('Observações')
                    ->rows(3),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();

                $registro = RuralPontoInteresse::create($data);

                Notification::make()->title('Ponto de Interesse Cadastrado!')->success()->send();

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
            ->modalHeading(fn () => 'Editar Local: ' . RuralPontoInteresse::find($this->ruralPontoInteresseAtivaId)?->nome)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Dados')
            ->fillForm(function (): array {
                $reg = RuralPontoInteresse::find($this->ruralPontoInteresseAtivaId);
                return [
                    'nome' => $reg?->nome,
                    'categoria' => $reg?->categoria,
                    'rural_localidade_id' => $reg?->rural_localidade_id,
                    'observacoes' => $reg?->observacoes,
                ];
            })
            ->form([
                TextInput::make('nome')->label('Nome do Local')->required()->maxLength(255),
                Select::make('categoria')->label('Categoria')->options(['Escola' => 'Escola / Educação', 'Saúde' => 'Posto de Saúde', 'Igreja' => 'Igreja / Templo', 'Turismo' => 'Ponto Turístico / Lazer', 'Comércio' => 'Comércio Local', 'Outro' => 'Outro'])->required(),
                Select::make('rural_localidade_id')->label('Localidade Base')->options(RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))->searchable()->required(),
                Textarea::make('observacoes')->label('Observações')->rows(3),
            ])
            ->action(function (array $data) {
                $reg = RuralPontoInteresse::find($this->ruralPontoInteresseAtivaId);
                if ($reg) {
                    $reg->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-rural_ponto_interesse', ['id' => $reg->id, 'name' => $data['nome']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_rural_ponto_interesse')
                    ->label('Mover Local')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-rural_ponto_interesse', id: $this->ruralPontoInteresseAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_rural_ponto_interesse')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        RuralPontoInteresse::where('id', $this->ruralPontoInteresseAtivaId)->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-rural_ponto_interesse-mapa', ['id' => $this->ruralPontoInteresseAtivaId]);
                        $this->dispatch('fechar-modal-filament');
                    })
            ]);
    }
}