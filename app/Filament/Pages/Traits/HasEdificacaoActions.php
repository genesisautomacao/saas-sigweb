<?php

namespace App\Filament\Pages\Traits;

use App\Models\Edificacao;
use App\Models\Lote;
use Dom\Text;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

trait HasEdificacaoActions
{
    /**
     * Alterna a visibilidade das edificações do lote ativo no mapa
     */
    public function toggleEdificacoesLote()
    {
        $this->mostrarEdificacoesLoteAtivo = !$this->mostrarEdificacoesLoteAtivo;

        if ($this->mostrarEdificacoesLoteAtivo && $this->loteAtivoId) {
            $edificacoes = Edificacao::where('lote_id', $this->loteAtivoId)
                ->select('id', 'geo')
                ->get()
                ->map(fn($edif) => [
                    'id' => $edif->id,
                    'geo' => $edif->geo_json,
                ])
                ->toArray();

            $this->dispatch('mostrar-edificacoes-lote', edificacoes: $edificacoes);
        } else {
            $this->dispatch('esconder-edificacoes-lote');
        }
    }

    /**
     * Ação: Criar Nova Edificação
     */
    public function criarEdificacaoAction(): Action
    {
        return Action::make('criarEdificacao')
            ->modalHeading('Cadastrar Nova Edificação')
            ->modalSubmitActionLabel('Salvar Edificação')
            ->modalWidth('md')
            ->form([
                Select::make('tipo')
                    ->label('Tipo de Construção')
                    ->options(['Alvenaria' => 'Alvenaria', 'Madeira' => 'Madeira', 'Mista' => 'Mista'])
                    ->required(),
                Select::make('estado_conservacao')
                    ->label('Estado de Conservação')
                    ->options(['Bom' => 'Bom', 'Regular' => 'Regular', 'Ruim' => 'Ruim'])
                    ->required(),
                \Filament\Forms\Components\TextInput::make('pavimento')
                    ->label('Nº de Pavimentos')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(99)
                    ->nullable(),

            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['lote_id'] = $this->loteAtivoId;
                $data['code'] = (string) Str::uuid();

                $edif = Edificacao::create($data);

                DB::statement("UPDATE edificacoes SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$edif->id]);

                $this->loteAreaConstruida = (float) Edificacao::where('lote_id', $this->loteAtivoId)->sum('area_geo');

                Notification::make()->title('Edificação Criada!')->success()->send();

                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');

                $this->mostrarEdificacoesLoteAtivo = false;
                $this->toggleEdificacoesLote();
            });
    }

    /**
     * Ação: Opções da Edificação (Modal de Edição/Exclusão)
     */
    public function opcoesEdificacaoAction(): Action
    {
        return Action::make('opcoesEdificacao')
            ->hiddenLabel()
            ->modalHeading(fn() => 'Edificação #' . $this->edificacaoAtivaId)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $edif = Edificacao::find($this->edificacaoAtivaId);
                return [
                    'tipo'               => $edif ? $edif->tipo : null,
                    'estado_conservacao' => $edif ? $edif->estado_conservacao : null,
                    'pavimento'          => $edif ? $edif->pavimento : null,
                    'area_geo'          => $edif ? $edif->area_geo : null,
                ];
            })
            ->form([
                Select::make('tipo')
                    ->label('Tipo de Construção')
                    ->options(['Alvenaria' => 'Alvenaria', 'Madeira' => 'Madeira', 'Mista' => 'Mista'])->required(),
                Select::make('estado_conservacao')
                    ->label('Estado de Conservação')
                    ->options(['Bom' => 'Bom', 'Regular' => 'Regular', 'Ruim' => 'Ruim'])->required(),
                \Filament\Forms\Components\TextInput::make('pavimento')
                    ->label('Nº de Pavimentos')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(99)
                    ->nullable(),
                \Filament\Forms\Components\TextInput::make('area_geo')
                    ->label('Área (m²)')
                    ->readOnly(),
            ])
            ->action(function (array $data) {
                $edif = Edificacao::find($this->edificacaoAtivaId);
                if ($edif) {
                    $edif->update($data);
                    Notification::make()->title('Dados Atualizados!')->success()->send();
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geometria')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->showFicha = false;
                        $this->dispatch('iniciar-edicao-geometria-edificacao', id: $this->edificacaoAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),

                Action::make('excluir_edif')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        Edificacao::where('id', $this->edificacaoAtivaId)->delete();
                        Notification::make()->title('Edificação Excluída!')->success()->send();
                        $this->mostrarEdificacoesLoteAtivo = false;
                        $this->toggleEdificacoesLote();
                    }),
            ]);
    }
}
