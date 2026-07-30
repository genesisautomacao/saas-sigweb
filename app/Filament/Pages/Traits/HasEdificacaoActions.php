<?php

namespace App\Filament\Pages\Traits;

use App\Models\Edificacao;
use App\Models\Lote;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HasEdificacaoActions
{
    /**
     * Alterna a visibilidade das edificações do lote ativo no mapa
     */
    public function toggleEdificacoesLote()
    {
        $this->mostrarEdificacoesLoteAtivo = ! $this->mostrarEdificacoesLoteAtivo;

        if ($this->mostrarEdificacoesLoteAtivo && $this->loteAtivoId) {
            $edificacoes = Edificacao::where('lote_id', $this->loteAtivoId)
                ->select('id', 'geo')
                ->get()
                ->map(fn ($edif) => [
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
                // R67-2 — rótulo/valores definidos pelo município
                \App\Services\Coleta\CampoDominioService::aplicar(Select::make('tipo')->required(), 'edificacao', 'tipo'),
                \App\Services\Coleta\CampoDominioService::aplicar(Select::make('tp_construcao')->required(), 'edificacao', 'tp_construcao'),
                \App\Services\Coleta\CampoDominioService::aplicar(
                    \Filament\Forms\Components\TextInput::make('caracteristica_construcao')
                        ->placeholder('Ex: Pavimento 1, Anexo, Edícula...')
                        ->maxLength(255)
                        ->nullable(),
                    'edificacao', 'caracteristica_construcao'
                ),
                \App\Services\Coleta\CampoDominioService::aplicar(Select::make('estado_conservacao')->required(), 'edificacao', 'estado_conservacao'),
                \App\Services\Coleta\CampoDominioService::aplicar(
                    \Filament\Forms\Components\TextInput::make('pavimento')->numeric()->minValue(1)->maxValue(99)->nullable(),
                    'edificacao', 'pavimento'
                ),

                // R67-1 — campos criados pelo município
                \Filament\Forms\Components\Section::make('Campos do Município')
                    ->visible(fn () => \App\Services\Coleta\CampoCustomizadoService::definicoes('edificacao')->isNotEmpty())
                    ->schema(fn () => \App\Services\Coleta\CampoCustomizadoService::componentes('edificacao'))
                    ->columnSpanFull(),

            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['lote_id'] = $this->loteAtivoId;
                $data['code'] = (string) Str::uuid();

                $edif = Edificacao::create($data);

                DB::statement('UPDATE edificacoes SET area_geo = ST_Area(geo::geography) WHERE id = ?', [$edif->id]);

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
            ->modalHeading(fn () => 'Edificação #'.$this->edificacaoAtivaId)
            ->modalWidth('xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $edif = Edificacao::find($this->edificacaoAtivaId);

                return [
                    'tipo' => $edif?->tipo,
                    'tp_construcao' => $edif?->tp_construcao,
                    'caracteristica_construcao' => $edif?->caracteristica_construcao,
                    'estado_conservacao' => $edif?->estado_conservacao,
                    'pavimento' => $edif?->pavimento,
                    'area_geo' => $edif?->area_geo,
                    'dados_customizados' => $edif?->dados_customizados ?? [], // R67-1
                ];
            })
            ->form([
                // R67-2 — rótulo/valores definidos pelo município
                \App\Services\Coleta\CampoDominioService::aplicar(Select::make('tipo')->required(), 'edificacao', 'tipo'),
                \App\Services\Coleta\CampoDominioService::aplicar(Select::make('tp_construcao')->required(), 'edificacao', 'tp_construcao'),
                \App\Services\Coleta\CampoDominioService::aplicar(
                    \Filament\Forms\Components\TextInput::make('caracteristica_construcao')
                        ->placeholder('Ex: Pavimento 1, Anexo, Edícula...')
                        ->maxLength(255)
                        ->nullable(),
                    'edificacao', 'caracteristica_construcao'
                ),
                \App\Services\Coleta\CampoDominioService::aplicar(Select::make('estado_conservacao')->required(), 'edificacao', 'estado_conservacao'),
                \App\Services\Coleta\CampoDominioService::aplicar(
                    \Filament\Forms\Components\TextInput::make('pavimento')->numeric()->minValue(1)->maxValue(99)->nullable(),
                    'edificacao', 'pavimento'
                ),
                \Filament\Forms\Components\TextInput::make('area_geo')
                    ->label('Área (m²)')
                    ->readOnly(),

                // R67-1 — campos criados pelo município
                \Filament\Forms\Components\Section::make('Campos do Município')
                    ->visible(fn () => \App\Services\Coleta\CampoCustomizadoService::definicoes('edificacao')->isNotEmpty())
                    ->schema(fn () => \App\Services\Coleta\CampoCustomizadoService::componentes('edificacao'))
                    ->columnSpanFull(),
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
