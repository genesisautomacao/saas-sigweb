<?php

namespace App\Filament\Pages\Traits;

use App\Models\RuralPropriedade;
use App\Models\RuralLocalidade;
use App\Models\Pessoa; // <-- Não esqueça de importar a model de Pessoa
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

trait HasRuralPropriedadeActions
{
    public ?int $ruralPropriedadeAtivaId = null;

    public function criarRuralPropriedadeAction(): Action
    {
        return Action::make('criarRuralPropriedade')
            ->modalHeading('Cadastrar Propriedade Rural')
            ->modalSubmitActionLabel('Salvar Propriedade')
            ->modalWidth('xl')
            ->form([
                TextInput::make('nome_propriedade')
                    ->label('Nome da Propriedade (CAR)')
                    ->required()
                    ->maxLength(255),
                Select::make('rural_localidade_id')
                    ->label('Localidade / Distrito')
                    ->options(RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))
                    ->default(fn() => $this->ruralLocalidadePreSelecionadaId) // <-- INJEÇÃO AUTOMÁTICA
                    ->searchable()
                    ->required(),
                Select::make('pessoa_id')
                    ->label('Proprietário Responsável')
                    ->options(Pessoa::where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->searchable(),
                TextInput::make('codigo_incra')->label('Código INCRA')->maxLength(255),
                TextInput::make('codigo_car')->label('Código CAR')->maxLength(255),
                TextInput::make('codigo_sigef')->label('Código SIGEF')->maxLength(255),
            ])
            ->action(function (array $data) {

                // 🛑 REGRA 2: Se o usuário trocar o Select manualmente, verifica se a localidade escolhida cobre o desenho
                $polyWKT = "ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($this->geometriaRascunho) . "'), 4326)";

                $validacao = DB::selectOne("
                    SELECT ST_Area(ST_Difference(
                        $polyWKT,
                        (SELECT geo::geometry FROM rural_localidades WHERE id = ?)
                    )::geography) as area_fora
                ", [$data['rural_localidade_id']]);

                if ($validacao && $validacao->area_fora > 1.0) {
                    Notification::make()
                        ->title('Incompatibilidade Espacial')
                        ->body('A área desenhada não está contida dentro da Localidade que você escolheu na lista. Verifique a seleção.')
                        ->danger()->send();

                    // Mantém a modal aberta para o usuário corrigir a seleção
                    throw new \Filament\Support\Exceptions\Halt();
                }

                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();

                $registro = RuralPropriedade::create($data);
                DB::statement("UPDATE rural_propriedades SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$registro->id]);

                Notification::make()->title('Propriedade Cadastrada!')->success()->send();

                $this->dispatch('adicionar-rural_propriedade-mapa', [
                    'id' => $registro->id,
                    'name' => $registro->nome_propriedade,
                    'geo' => $this->geometriaRascunho
                ]);
                $this->dispatch('limpar-rascunho-mapa');
            });
    }

    public function opcoesRuralPropriedadeAction(): Action
    {
        return Action::make('opcoesRuralPropriedade')
            ->hiddenLabel()
            ->modalHeading(fn() => 'Editar Propriedade: ' . RuralPropriedade::find($this->ruralPropriedadeAtivaId)?->nome_propriedade)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Dados')
            ->fillForm(function (): array {
                $reg = RuralPropriedade::find($this->ruralPropriedadeAtivaId);
                return [
                    'nome_propriedade' => $reg?->nome_propriedade,
                    'rural_localidade_id' => $reg?->rural_localidade_id,
                    'pessoa_id' => $reg?->pessoa_id,
                    'codigo_incra' => $reg?->codigo_incra,
                    'codigo_car' => $reg?->codigo_car,
                    'codigo_sigef' => $reg?->codigo_sigef,
                ];
            })
            ->form([
                TextInput::make('nome_propriedade')->label('Nome da Propriedade (CAR)')->required()->maxLength(255),
                Select::make('rural_localidade_id')
                    ->label('Localidade / Distrito')
                    ->options(RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('nome', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('pessoa_id')
                    ->label('Proprietário Responsável')
                    ->options(Pessoa::where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->searchable(),
                TextInput::make('codigo_incra')->label('Código INCRA')->maxLength(255),
                TextInput::make('codigo_car')->label('Código CAR')->maxLength(255),
                TextInput::make('codigo_sigef')->label('Código SIGEF')->maxLength(255),
            ])
            ->action(function (array $data) {
                $reg = RuralPropriedade::find($this->ruralPropriedadeAtivaId);
                if ($reg) {
                    $reg->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                    $this->dispatch('atualizar-label-rural_propriedade', ['id' => $reg->id, 'name' => $data['nome_propriedade']]);
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geo_rural_propriedade')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-rural_propriedade', id: $this->ruralPropriedadeAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                Action::make('excluir_rural_propriedade')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        RuralPropriedade::where('id', $this->ruralPropriedadeAtivaId)->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-rural_propriedade-mapa', ['id' => $this->ruralPropriedadeAtivaId]);
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }
}
