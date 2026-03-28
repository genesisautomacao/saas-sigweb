<?php

namespace App\Filament\Pages\Traits;

use App\Models\RuralEstrada;
use App\Models\RuralLocalidade;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

trait HasRuralEstradaActions
{
    public ?int $ruralEstradaAtivaId = null;

    public function criarRuralEstradaAction(): Action
    {
        return Action::make('criarRuralEstrada')
            ->modalHeading('Cadastrar Estrada / Vicinal')
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Estrada')
            ->form([
                TextInput::make('nome')->label('Nome da Estrada ou Trecho')->required()->maxLength(255),
                Select::make('rural_localidade_id')
                    ->label('Localidade / Distrito Base')
                    ->options(RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))
                    ->default(fn() => $this->ruralLocalidadePreSelecionadaId) // Injeção automática da trava
                    ->searchable()
                    ->required(),
                Select::make('tipo')
                    ->label('Tipo da Via')
                    ->options(['Vicinal' => 'Vicinal', 'Municipal' => 'Municipal', 'Estadual' => 'Estadual', 'Servidão' => 'Servidão'])
                    ->required(),
                Select::make('tipo_pavimento')
                    ->label('Pavimento')
                    ->options(['Terra' => 'Terra', 'Cascalho' => 'Cascalho', 'Asfalto' => 'Asfalto', 'Calçamento' => 'Calçamento'])
                    ->required(),
                Select::make('condicao_trafego')
                    ->label('Condição de Tráfego')
                    ->options(['Boa' => 'Boa', 'Regular' => 'Regular', 'Ruim' => 'Ruim', 'Intransitável' => 'Intransitável'])
                    ->required(),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();

                $registro = RuralEstrada::create($data);
                
                // 🛑 MÁGICA POSTGIS: Calcula o comprimento da linha em metros e atualiza o campo extensao_geo
                DB::statement("UPDATE rural_estradas SET extensao_geo = ST_Length(geo::geography) WHERE id = ?", [$registro->id]);

                Notification::make()->title('Estrada Cadastrada!')->success()->send();

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
            ->modalHeading(fn () => 'Editar Estrada: ' . RuralEstrada::find($this->ruralEstradaAtivaId)?->nome)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Dados')
            ->fillForm(function (): array {
                $reg = RuralEstrada::find($this->ruralEstradaAtivaId);
                return [
                    'nome' => $reg?->nome,
                    'rural_localidade_id' => $reg?->rural_localidade_id,
                    'tipo' => $reg?->tipo,
                    'tipo_pavimento' => $reg?->tipo_pavimento,
                    'condicao_trafego' => $reg?->condicao_trafego,
                ];
            })
            ->form([
                TextInput::make('nome')->label('Nome da Estrada ou Trecho')->required()->maxLength(255),
                Select::make('rural_localidade_id')
                    ->label('Localidade / Distrito Base')
                    ->options(RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('tipo')->label('Tipo da Via')->options(['Vicinal' => 'Vicinal', 'Municipal' => 'Municipal', 'Estadual' => 'Estadual', 'Servidão' => 'Servidão'])->required(),
                Select::make('tipo_pavimento')->label('Pavimento')->options(['Terra' => 'Terra', 'Cascalho' => 'Cascalho', 'Asfalto' => 'Asfalto', 'Calçamento' => 'Calçamento'])->required(),
                Select::make('condicao_trafego')->label('Condição de Tráfego')->options(['Boa' => 'Boa', 'Regular' => 'Regular', 'Ruim' => 'Ruim', 'Intransitável' => 'Intransitável'])->required(),
            ])
            ->action(function (array $data) {
                $reg = RuralEstrada::find($this->ruralEstradaAtivaId);
                if ($reg) {
                    $reg->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-rural_estrada', ['id' => $reg->id, 'name' => $data['nome']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_rural_estrada')
                    ->label('Traçado')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-rural_estrada', id: $this->ruralEstradaAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_rural_estrada')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        RuralEstrada::where('id', $this->ruralEstradaAtivaId)->delete();
                        Notification::make()->title('Excluída!')->success()->send();
                        $this->dispatch('remover-rural_estrada-mapa', ['id' => $this->ruralEstradaAtivaId]);
                        $this->dispatch('fechar-modal-filament');
                    })
            ]);
    }
}