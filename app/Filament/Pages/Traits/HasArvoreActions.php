<?php

namespace App\Filament\Pages\Traits;

use App\Models\Arvore;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait HasArvoreActions
{
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

                if (!empty($logradourosIds)) {
                    $arvore->logradouros()->sync($logradourosIds);
                }

                Notification::make()->title('Árvore Cadastrada com Sucesso!')->success()->send();

                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');
                $this->dispatch('atualizar-camada-arvores');
            });
    }

    /**
     * 🛑 HUB DE AÇÕES DINÂMICO DA ÁRVORE
     */
    public function opcoesArvoreAction(): Action
    {
        return Action::make('opcoesArvore')
            ->hiddenLabel()
            ->modalHeading(function () {
                $arvore = Arvore::find($this->arvoreAtivaId);
                return 'Árvore / Indivíduo Arbóreo #' . ($arvore ? $arvore->sequential_id : '');
            })
            ->modalDescription('Selecione a operação que deseja realizar:')
            ->modalWidth('sm')
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->form(function () {

                // 🟢 VERIFICA SE EXISTE CHAMADO ABERTO
                $solicitacaoAberta = \App\Models\SolicitacaoManutencao::where('asset_type', \App\Models\Arvore::class)
                    ->where('asset_id', $this->arvoreAtivaId)
                    ->whereIn('status', ['pendente', 'analise', 'aprovada_os'])
                    ->first();

                $actions = [];

                $actions[] = \Filament\Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('editar_ficha')
                        ->label('Ver / Editar Ficha Completa')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->action(fn() => $this->replaceMountedAction('editarArvore')),
                ])->fullWidth();

                $actions[] = \Filament\Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('geometria')
                        ->label('Alterar Geometria (Mover)')
                        ->icon('heroicon-o-map')
                        ->color('warning')
                        ->action(function () {
                            $this->dispatch('iniciar-edicao-geometria-arvore', id: $this->arvoreAtivaId);
                            $this->dispatch('fechar-modal-filament');
                        }),
                ])->fullWidth();

                // 🛑 A MÁGICA: ALTERNA O BOTÃO
                if ($solicitacaoAberta) {
                    $actions[] = \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('ver_manutencao')
                            ->label('Ver Solicitação Aberta (#' . $solicitacaoAberta->sequential_id . ')')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->color('danger')
                            ->url(fn() => \App\Filament\Resources\SolicitacaoManutencaoResource::getUrl('edit', ['record' => $solicitacaoAberta->id]))
                            ->openUrlInNewTab(),
                    ])->fullWidth();
                } else {
                    $actions[] = \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('manutencao')
                            ->label('Solicitar Serviço / Manejo')
                            ->icon('heroicon-o-scissors')
                            ->color('success')
                            ->action(fn() => $this->replaceMountedAction('manutencaoArvore')),
                    ])->fullWidth();
                }

                $actions[] = \Filament\Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('excluir')
                        ->label('Excluir Árvore')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function () {
                            Arvore::where('id', $this->arvoreAtivaId)->delete();
                            Notification::make()->title('Árvore Excluída!')->success()->send();
                            $this->dispatch('atualizar-camada-arvores');
                            $this->dispatch('fechar-modal-filament');
                        }),
                ])->fullWidth();

                return $actions;
            });
    }

    /**
     * 🛑 O FORMULÁRIO REAL (Que foi separado do Hub)
     */
    public function editarArvoreAction(): Action
    {
        return Action::make('editarArvore')
            ->model(\App\Models\Arvore::class)
            ->hiddenLabel()
            ->modalHeading(function () {
                $arvore = Arvore::find($this->arvoreAtivaId);
                return 'Editar Ficha - Árvore #' . ($arvore ? $arvore->sequential_id : '');
            })
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $arvore = Arvore::with('logradouros')->find($this->arvoreAtivaId);
                if (!$arvore)
                    return [];

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
            });
    }

    /**
     * 🛑 CRIA A SOLICITAÇÃO REAL NO BANCO DE DADOS
     */
    public function manutencaoArvoreAction(): Action
    {
        return Action::make('manutencaoArvore')
            ->hiddenLabel()
            ->modalHeading('Abertura de Chamado - Manejo Arbóreo')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Abrir Chamado')
            ->form([
                \Filament\Forms\Components\Select::make('tipo_servico')
                    ->label('Tipo de Serviço / Manejo')
                    ->options([
                        'Poda de Limpeza' => 'Poda de Limpeza (Galhos Secos)',
                        'Poda de Elevação' => 'Poda de Elevação de Copa',
                        'Poda por Interferência' => 'Poda por Interferência (Fios/Placas)',
                        'Supressão (Corte)' => 'Supressão / Corte (Morta/Risco)',
                        'Análise' => 'Análise Fitossanitária (Laudo)',
                    ])
                    ->required(),

                \Filament\Forms\Components\Select::make('prioridade')
                    ->label('Nível de Prioridade')
                    ->options([
                        'baixa' => '🟢 Baixa (Rotina)',
                        'media' => '🟡 Média (Normal)',
                        'alta' => '🔴 Alta (Urgência)',
                        'critica' => '⚫ Crítica (Risco de Queda)',
                    ])
                    ->default('media')
                    ->required(),

                \Filament\Forms\Components\TextInput::make('solicitante_nome')
                    ->label('Nome do Solicitante (Opcional)'),

                \Filament\Forms\Components\Textarea::make('observacao')
                    ->label('Descrição / Justificativa')
                    ->rows(3),
            ])
            ->action(function (array $data) {
                // SALVA A SOLICITAÇÃO DE VERDADE CONECTADA À ÁRVORE!
                \App\Models\SolicitacaoManutencao::create([
                    'tenant_id' => $this->tenantId,
                    'asset_type' => \App\Models\Arvore::class,
                    'asset_id' => $this->arvoreAtivaId,
                    'tipo_servico' => $data['tipo_servico'],
                    'prioridade' => $data['prioridade'],
                    'status' => 'pendente',
                    'solicitante_nome' => $data['solicitante_nome'] ?? null,
                    'observacao' => $data['observacao'] ?? null,
                ]);

                Notification::make()->title('Chamado Aberto com Sucesso!')->success()->send();

                $this->dispatch('atualizar-camada-arvores');
                $this->dispatch('fechar-modal-filament');
            });
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