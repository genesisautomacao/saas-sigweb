<?php

namespace App\Filament\Pages\Traits;

use App\Models\Poste;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait HasPosteActions
{
    public ?int $posteAtivoId = null;

    /**
     * Ação: Criar Novo Poste Diretamente pelo Mapa
     */
    public function criarPosteAction(): Action
    {
        return Action::make('criarPoste')
            ->model(\App\Models\Poste::class)
            ->modalHeading('Cadastrar Novo Poste / Ponto de Luz')
            ->modalSubmitActionLabel('Salvar Poste')
            ->modalWidth('2xl')
            ->form($this->getPosteFormSchema())
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();

                $logradourosIds = $data['logradouros'] ?? [];
                unset($data['logradouros']);

                $poste = Poste::create($data);

                if (!empty($logradourosIds)) {
                    $poste->logradouros()->sync($logradourosIds);
                }

                Notification::make()->title('Poste Criado com Sucesso!')->success()->send();

                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');
                $this->dispatch('atualizar-camada-postes');
            });
    }

   /**
     * 🛑 HUB DE AÇÕES DINÂMICO (Com inteligência de Manutenção)
     */
    public function opcoesPosteAction(): Action
    {
        return Action::make('opcoesPoste')
            ->hiddenLabel()
            ->modalHeading(function () {
                $poste = Poste::find($this->posteAtivoId);
                return 'Ponto de Iluminação #' . ($poste ? $poste->sequential_id : '');
            })
            ->modalDescription('Selecione a operação que deseja realizar:')
            ->modalWidth('sm')
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->form(function () {
                // 🟢 VERIFICA SE EXISTE CHAMADO ABERTO PARA ESTE POSTE
                $solicitacaoAberta = \App\Models\SolicitacaoManutencao::where('asset_type', \App\Models\Poste::class)
                    ->where('asset_id', $this->posteAtivoId)
                    ->whereIn('status', ['pendente', 'analise', 'aprovada_os'])
                    ->first();

                $actions = [];

                // 1. Botão Ver/Editar Ficha
                $actions[] = \Filament\Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('editar_ficha')
                        ->label('Ver / Editar Ficha Completa')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->action(fn () => $this->replaceMountedAction('editarPoste')),
                ])->fullWidth();

                // 2. Botão Geometria
                $actions[] = \Filament\Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('geometria')
                        ->label('Alterar Geometria (Mover)')
                        ->icon('heroicon-o-map')
                        ->color('warning')
                        ->action(function () {
                            $this->dispatch('iniciar-edicao-geometria-poste', id: $this->posteAtivoId);
                            $this->dispatch('fechar-modal-filament');
                        }),
                ])->fullWidth();

                // 3. 🛑 A MÁGICA: ALTERNA O BOTÃO COM BASE NO STATUS 🛑
                if ($solicitacaoAberta) {
                    $actions[] = \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('ver_manutencao')
                            ->label('Ver Solicitação Aberta (#' . $solicitacaoAberta->sequential_id . ')')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->color('danger')
                            // Abre a tela de edição da solicitação em NOVA ABA para não perder o mapa!
                            ->url(fn () => \App\Filament\Resources\SolicitacaoManutencaoResource::getUrl('edit', ['record' => $solicitacaoAberta->id]))
                            ->openUrlInNewTab(),
                    ])->fullWidth();
                } else {
                    $actions[] = \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('manutencao')
                            ->label('Solicitar Manutenção / OS')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->color('success')
                            ->action(fn () => $this->replaceMountedAction('manutencaoPoste')),
                    ])->fullWidth();
                }

                // 4. Botão Excluir
                $actions[] = \Filament\Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('excluir')
                        ->label('Excluir Equipamento')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function () {
                            Poste::where('id', $this->posteAtivoId)->delete();
                            Notification::make()->title('Poste Excluído!')->success()->send();
                            $this->dispatch('atualizar-camada-postes');
                            $this->dispatch('fechar-modal-filament');
                        }),
                ])->fullWidth();

                return $actions;
            });
    }

    

    /**
     * 🛑 O FORMULÁRIO REAL (Que foi separado do Hub)
     */
    public function editarPosteAction(): Action
    {
        return Action::make('editarPoste')
            ->model(\App\Models\Poste::class)
            ->hiddenLabel()
            ->modalHeading(function () {
                $poste = Poste::find($this->posteAtivoId);
                return 'Editar Ficha - Poste #' . ($poste ? $poste->sequential_id : '');
            })
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                $poste = Poste::with('logradouros')->find($this->posteAtivoId);
                if (!$poste)
                    return [];

                return [
                    'logradouros' => $poste->logradouros->pluck('id')->toArray(),
                    'address' => $poste->address,
                    'tipo_poste_id' => $poste->tipo_poste_id,
                    'structural_condition' => $poste->structural_condition,
                    'height' => $poste->height,
                    'installation_date' => $poste->installation_date,
                    'luminaire_type' => $poste->luminaire_type,
                    'lamp_power' => $poste->lamp_power,
                    'lamp_quantity' => $poste->lamp_quantity,
                    'luminaire_height' => $poste->luminaire_height,
                    'observations' => $poste->observations,
                ];
            })
            ->form($this->getPosteFormSchema())
            ->action(function (array $data) {
                $poste = Poste::find($this->posteAtivoId);
                if ($poste) {
                    $logradourosIds = $data['logradouros'] ?? [];
                    unset($data['logradouros']);

                    $poste->update($data);
                    $poste->logradouros()->sync($logradourosIds);

                    Notification::make()->title('Poste Atualizado!')->success()->send();
                    $this->dispatch('atualizar-camada-postes');
                }
            });
        // 🛑 Agora a edição só tem "Salvar" e "Cancelar" padrão do Filament, super limpo!
    }

/**
     * 🛑 CRIA A SOLICITAÇÃO REAL NO BANCO DE DADOS
     */
    public function manutencaoPosteAction(): Action
    {
        return Action::make('manutencaoPoste')
            ->hiddenLabel()
            ->modalHeading('Abertura de Chamado - Ordem de Serviço')
            ->modalDescription('Informe o problema reportado para este ponto de luz.')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Abrir Chamado')
            ->form([
                \Filament\Forms\Components\Select::make('tipo_servico')
                    ->label('Tipo de Ocorrência')
                    ->options([
                        'Lâmpada Apagada' => 'Lâmpada Apagada (À Noite)',
                        'Lâmpada Acesa' => 'Lâmpada Acesa (De Dia)',
                        'Lâmpada Oscilando' => 'Lâmpada Oscilando/Piscando',
                        'Luminária Quebrada' => 'Luminária Quebrada',
                        'Poste Abalroado' => 'Poste Atingido por Veículo',
                    ])
                    ->required(),

                \Filament\Forms\Components\Select::make('prioridade')
                    ->label('Nível de Prioridade')
                    ->options([
                        'baixa' => '🟢 Baixa (Rotina)',
                        'media' => '🟡 Média (Normal)',
                        'alta' => '🔴 Alta (Emergência)',
                        'critica' => '⚫ Crítica (Risco)',
                    ])
                    ->default('media')
                    ->required(),
                
                \Filament\Forms\Components\TextInput::make('solicitante_nome')
                    ->label('Nome do Solicitante (Opcional)'),

                \Filament\Forms\Components\Textarea::make('observacao')
                    ->label('Descrição do Problema')
                    ->rows(3),
            ])
            ->action(function (array $data) {
                // SALVA A SOLICITAÇÃO DE VERDADE CONECTADA AO POSTE!
                \App\Models\SolicitacaoManutencao::create([
                    'tenant_id' => $this->tenantId,
                    'asset_type' => \App\Models\Poste::class,
                    'asset_id' => $this->posteAtivoId,
                    'tipo_servico' => $data['tipo_servico'],
                    'prioridade' => $data['prioridade'],
                    'status' => 'pendente',
                    'solicitante_nome' => $data['solicitante_nome'] ?? null,
                    'observacao' => $data['observacao'] ?? null,
                ]);

                Notification::make()->title('Chamado Aberto com Sucesso!')->success()->send();
                
                // Manda o mapa atualizar para o poste mudar de cor!
                $this->dispatch('atualizar-camada-postes'); 
                $this->dispatch('fechar-modal-filament');
            });
    }

    /**
     * Helper: Centraliza os campos do formulário (Sem alterações aqui)
     */
    protected function getPosteFormSchema(): array
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
                        ->label('Referência (Opcional)')
                        ->placeholder('Ex: Em frente ao nº 120, Esquina com a padaria...')
                        ->maxLength(255),
                ])->columns(1),

            \Filament\Forms\Components\Grid::make(2)->schema([
                \Filament\Forms\Components\Select::make('tipo_poste_id')
                    ->label('Tipo de Poste')
                    ->relationship('tipoPoste', 'name')
                    ->required()
                    ->createOptionForm([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->label('Novo Tipo')
                            ->required(),
                    ]),

                \Filament\Forms\Components\Select::make('structural_condition')
                    ->label('Condição Estrutural')
                    ->options([
                        'Bom' => 'Bom',
                        'Regular' => 'Regular',
                        'Ruim' => 'Ruim',
                    ])
                    ->default('Regular')
                    ->required(),

                \Filament\Forms\Components\TextInput::make('height')
                    ->label('Altura do Poste (m)')
                    ->numeric(),

                \Filament\Forms\Components\DatePicker::make('installation_date')
                    ->label('Data de Instalação')
                    ->displayFormat('d/m/Y'),
            ]),

            \Filament\Forms\Components\Section::make('Dados da Luminária')->schema([
                \Filament\Forms\Components\Select::make('luminaire_type')
                    ->label('Tipo de Luminária')
                    ->options([
                        'LED' => 'LED',
                        'Vapor de Sódio' => 'Vapor de Sódio',
                        'Vapor Metálico' => 'Vapor Metálico',
                        'Mercúrio' => 'Mercúrio',
                        'Outros' => 'Outros',
                    ]),
                \Filament\Forms\Components\TextInput::make('lamp_power')
                    ->label('Potência (ex: 150W)'),
                \Filament\Forms\Components\TextInput::make('lamp_quantity')
                    ->label('Qtd. de Lâmpadas')
                    ->numeric(),
                \Filament\Forms\Components\TextInput::make('luminaire_height')
                    ->label('Altura da Luminária (m)')
                    ->numeric(),
            ])->columns(2),

            \Filament\Forms\Components\Textarea::make('observations')
                ->label('Anotações Técnicas')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }
}