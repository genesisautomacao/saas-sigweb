<?php

namespace App\Filament\Pages\Traits;

use App\Models\Arvore;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait HasArvoreActions
{
    // Guarda qual árvore foi clicada no mapa
    public ?int $arvoreAtivaId = null;

    /**
     * Ação: Criar Nova Árvore Diretamente pelo Mapa
     */
    public function criarArvoreAction(): Action
    {
        return Action::make('criarArvore')
            ->model(\App\Models\Arvore::class)
            ->modalHeading('Cadastrar Nova Árvore')
            ->modalSubmitActionLabel('Salvar Árvore')
            ->modalWidth('2xl')
            ->form($this->getArvoreFormSchema())
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho; 
                $data['code'] = (string) Str::uuid();

                $logradourosIds = $data['logradouros'] ?? [];
                unset($data['logradouros']);

                $arvore = Arvore::create($data);

                // Salva a rua mais próxima na tabela pivô
                if (!empty($logradourosIds)) {
                    $arvore->logradouros()->sync($logradourosIds);
                }

                Notification::make()->title('Árvore Cadastrada!')->success()->send();

                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');
                $this->dispatch('atualizar-camada-arvores');
            });
    }

    /**
     * Ação: Opções e Edição Completa da Árvore
     */
    public function opcoesArvoreAction(): Action
    {
        return Action::make('opcoesArvore')
            ->model(\App\Models\Arvore::class)
            ->hiddenLabel()
            ->modalHeading(function () {
                $arvore = Arvore::find($this->arvoreAtivaId);
                return 'Editar Árvore #' . ($arvore ? $arvore->sequential_id : $this->arvoreAtivaId);
            })
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $arvore = Arvore::with('logradouros')->find($this->arvoreAtivaId);
                if (!$arvore) return [];
                
                return [
                    'logradouros' => $arvore->logradouros->pluck('id')->toArray(),
                    'address' => $arvore->address,
                    'botanical_species' => $arvore->botanical_species,
                    'botanical_family' => $arvore->botanical_family,
                    'size' => $arvore->size,
                    'trunk_diameter_dap' => $arvore->trunk_diameter_dap,
                    'canopy_diameter' => $arvore->canopy_diameter,
                    'total_height' => $arvore->total_height,
                    'canopy_height' => $arvore->canopy_height,
                    'phytosanitary_condition' => $arvore->phytosanitary_condition,
                    'general_state' => $arvore->general_state,
                    'root_system' => $arvore->root_system,
                    'urban_interferences' => $arvore->urban_interferences,
                    'risk_potential' => $arvore->risk_potential,
                    'observations' => $arvore->observations,
                ];
            })
            ->form($this->getArvoreFormSchema())
            ->action(function (array $data) {
                $arvore = Arvore::find($this->arvoreAtivaId);
                if ($arvore) {
                    $logradourosIds = $data['logradouros'] ?? [];
                    unset($data['logradouros']);

                    $arvore->update($data);
                    $arvore->logradouros()->sync($logradourosIds);

                    Notification::make()->title('Árvore Atualizada!')->success()->send();
                    $this->dispatch('atualizar-camada-arvores'); 
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geometria')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-arvore', id: $this->arvoreAtivaId);
                        $this->dispatch('fechar-modal-filament');
                    }),
                    
                Action::make('excluir_arvore')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function() {
                        Arvore::where('id', $this->arvoreAtivaId)->delete();
                        Notification::make()->title('Árvore Excluída!')->success()->send();
                        $this->dispatch('atualizar-camada-arvores');
                        $this->dispatch('fechar-modal-filament');
                    }),
            ]);
    }

    /**
     * Helper: Centraliza os campos do formulário para o Mapa
     */
    protected function getArvoreFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Section::make('Localização e Endereçamento')
                ->schema([
                    \Filament\Forms\Components\Select::make('logradouros')
                        ->label('Logradouro(s) Vinculado(s)')
                        ->options(function () {
                            return \App\Models\Logradouro::query()
                                ->where('tenant_id', $this->tenantId)
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->default(function () {
                            // RADAR: Busca a rua mais próxima ao abrir o modal
                            if ($this->geometriaRascunho && isset($this->geometriaRascunho['coordinates'])) {
                                $lon = $this->geometriaRascunho['coordinates'][0];
                                $lat = $this->geometriaRascunho['coordinates'][1];
                                
                                $nearest = \App\Models\Logradouro::query()
                                    ->where('tenant_id', $this->tenantId)
                                    ->whereNotNull('geo')
                                    ->orderByRaw("ST_DistanceSphere(geo, ST_SetSRID(ST_MakePoint(?, ?), 4326))", [$lon, $lat])
                                    ->first();

                                return $nearest ? [$nearest->id] : [];
                            }
                            return [];
                        }),

                    \Filament\Forms\Components\TextInput::make('address')
                        ->label('Referência Extra (Opcional)')
                        ->maxLength(255),
                ])->columns(1),

            \Filament\Forms\Components\Section::make('Dados Biométricos e Botânicos')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('botanical_species')->label('Espécie Botânica'),
                    \Filament\Forms\Components\TextInput::make('botanical_family')->label('Família Botânica'),
                    \Filament\Forms\Components\Select::make('size')
                        ->label('Porte')
                        ->options(['pequeno' => 'Pequeno', 'medio' => 'Médio', 'grande' => 'Grande']),
                    \Filament\Forms\Components\TextInput::make('trunk_diameter_dap')->label('DAP (cm)')->numeric(),
                    \Filament\Forms\Components\TextInput::make('canopy_diameter')->label('Diâmetro Copa (m)')->numeric(),
                    \Filament\Forms\Components\TextInput::make('total_height')->label('Altura Total (m)')->numeric(),
                    \Filament\Forms\Components\TextInput::make('canopy_height')->label('Altura Forquilha (m)')->numeric(),
                ])->columns(2),

            \Filament\Forms\Components\Section::make('Condições e Riscos')
                ->schema([
                    \Filament\Forms\Components\Select::make('phytosanitary_condition')
                        ->label('Condição Fitossanitária')
                        ->options(['Boa' => 'Boa', 'Regular' => 'Regular', 'Ruim' => 'Ruim', 'Morta' => 'Morta']),
                    \Filament\Forms\Components\TextInput::make('general_state')->label('Estado Geral'),
                    \Filament\Forms\Components\TextInput::make('root_system')->label('Sistema Radicular'),
                    \Filament\Forms\Components\TextInput::make('urban_interferences')->label('Interferências'),
                    \Filament\Forms\Components\Select::make('risk_potential')
                        ->label('Potencial de Risco')
                        ->options([1 => '1 - Muito Baixo', 2 => '2 - Baixo', 3 => '3 - Médio', 4 => '4 - Alto', 5 => '5 - Crítico']),
                ])->columns(2),

            \Filament\Forms\Components\Textarea::make('observations')->label('Anotações Técnicas')->rows(2)->columnSpanFull(),
        ];
    }
}