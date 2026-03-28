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
    public ?int $ruralEstradaPreSelecionadaId = null;

    public function criarRuralPonteAction(): Action
    {
        return Action::make('criarRuralPonte')
            ->modalHeading('Cadastrar Ponte Rural')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Ponte')
            ->form([
                TextInput::make('nome_referencia')->label('Nome / Referência da Ponte')->required()->maxLength(255),
                Select::make('rural_localidade_id')
                    ->label('Localidade / Distrito Base')
                    ->options(RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))
                    ->default(fn() => $this->ruralLocalidadePreSelecionadaId) // Injeção inteligente
                    ->searchable()
                    ->required(),
                Select::make('rural_estrada_id')
                    ->label('Estrada / Vicinal (Opcional)')
                    ->options(RuralEstrada::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))
                    ->default(fn() => $this->ruralEstradaPreSelecionadaId) // Injeção inteligente do PostGIS
                    ->searchable(),
                Select::make('material_construcao')
                    ->label('Material de Construção')
                    ->options(['Madeira' => 'Madeira', 'Concreto' => 'Concreto', 'Mista' => 'Mista', 'Metálica' => 'Metálica'])
                    ->required(),
                TextInput::make('capacidade_carga_toneladas')
                    ->label('Capacidade (Toneladas)')
                    ->numeric()
                    ->suffix('t'),
                Select::make('estado_conservacao')
                    ->label('Estado de Conservação')
                    ->options(['Bom' => 'Bom', 'Regular' => 'Regular', 'Ruim' => 'Ruim', 'Interditada' => 'Interditada'])
                    ->required(),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();

                $registro = RuralPonte::create($data);

                Notification::make()->title('Ponte Cadastrada!')->success()->send();

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
            ->modalHeading(fn () => 'Editar Ponte: ' . RuralPonte::find($this->ruralPonteAtivaId)?->nome_referencia)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Dados')
            ->fillForm(function (): array {
                $reg = RuralPonte::find($this->ruralPonteAtivaId);
                return [
                    'nome_referencia' => $reg?->nome_referencia,
                    'rural_localidade_id' => $reg?->rural_localidade_id,
                    'rural_estrada_id' => $reg?->rural_estrada_id,
                    'material_construcao' => $reg?->material_construcao,
                    'capacidade_carga_toneladas' => $reg?->capacidade_carga_toneladas,
                    'estado_conservacao' => $reg?->estado_conservacao,
                ];
            })
            ->form([
                TextInput::make('nome_referencia')->label('Nome / Referência da Ponte')->required()->maxLength(255),
                Select::make('rural_localidade_id')->label('Localidade Base')->options(RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))->searchable()->required(),
                Select::make('rural_estrada_id')->label('Estrada / Vicinal (Opcional)')->options(RuralEstrada::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))->searchable(),
                Select::make('material_construcao')->label('Material')->options(['Madeira' => 'Madeira', 'Concreto' => 'Concreto', 'Mista' => 'Mista', 'Metálica' => 'Metálica'])->required(),
                TextInput::make('capacidade_carga_toneladas')->label('Capacidade (Toneladas)')->numeric()->suffix('t'),
                Select::make('estado_conservacao')->label('Estado')->options(['Bom' => 'Bom', 'Regular' => 'Regular', 'Ruim' => 'Ruim', 'Interditada' => 'Interditada'])->required(),
            ])
            ->action(function (array $data) {
                $reg = RuralPonte::find($this->ruralPonteAtivaId);
                if ($reg) {
                    $reg->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-rural_ponte', ['id' => $reg->id, 'name' => $data['nome_referencia']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_rural_ponte')
                    ->label('Mover Ponte')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-rural_ponte', id: $this->ruralPonteAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_rural_ponte')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        RuralPonte::where('id', $this->ruralPonteAtivaId)->delete();
                        Notification::make()->title('Excluída!')->success()->send();
                        $this->dispatch('remover-rural_ponte-mapa', ['id' => $this->ruralPonteAtivaId]);
                        $this->dispatch('fechar-modal-filament');
                    })
            ]);
    }
}