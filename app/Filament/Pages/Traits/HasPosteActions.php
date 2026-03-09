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
     * 🛑 HUB DE AÇÕES (A Nova Modal Centralizadora Menorzinha)
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
            ->modalWidth('sm') // Modal pequena!
            ->modalSubmitAction(false) // Remove o botão salvar padrão
            ->modalCancelAction(false) // Remove o botão cancelar padrão
            ->form([
                // 1. Botão Ver/Editar Ficha
                \Filament\Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('editar_ficha')
                        ->label('Ver / Editar Ficha Completa')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->action(function () {
                            // MÁGICA: Substitui a modal atual pela modal de edição!
                            $this->replaceMountedAction('editarPoste');
                        }),
                ])->fullWidth(), // Força a empilhar verticalmente

                // 2. Botão Geometria
                \Filament\Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('geometria')
                        ->label('Alterar Geometria (Mover)')
                        ->icon('heroicon-o-map')
                        ->color('warning')
                        ->action(function () {
                            $this->dispatch('iniciar-edicao-geometria-poste', id: $this->posteAtivoId);
                            $this->dispatch('fechar-modal-filament');
                        }),
                ])->fullWidth(),

                // 3. Botão Manutenção (O Novo Módulo)
                \Filament\Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('manutencao')
                        ->label('Solicitar Manutenção / OS')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('success')
                        ->action(function () {
                            $this->replaceMountedAction('manutencaoPoste');
                        }),
                ])->fullWidth(),

                // 4. Botão Excluir
                \Filament\Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('excluir')
                        ->label('Excluir Equipamento')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Excluir Poste')
                        ->modalDescription('Tem certeza que deseja remover este poste do mapa? Esta ação não pode ser desfeita.')
                        ->modalSubmitActionLabel('Sim, excluir')
                        ->action(function () {
                            Poste::where('id', $this->posteAtivoId)->delete();
                            Notification::make()->title('Poste Excluído!')->success()->send();
                            $this->dispatch('atualizar-camada-postes');
                            $this->dispatch('fechar-modal-filament');
                        }),
                ])->fullWidth(),
            ]);
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
     * 🛑 O SIMULADOR DO MÓDULO DE MANUTENÇÃO (Apenas para teste visual)
     */
    public function manutencaoPosteAction(): Action
    {
        return Action::make('manutencaoPoste')
            ->model(\App\Models\Poste::class)
            ->hiddenLabel()
            ->modalHeading('Abertura de Chamado - Ordem de Serviço')
            ->modalDescription('Informe o problema reportado para este ponto de luz.')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Abrir Chamado')
            ->form([
                \Filament\Forms\Components\Select::make('defeito')
                    ->label('Tipo de Ocorrência')
                    ->options([
                        'apagada' => 'Lâmpada Apagada (À Noite)',
                        'acesa' => 'Lâmpada Acesa (De Dia)',
                        'oscilando' => 'Lâmpada Oscilando/Piscando',
                        'quebrada' => 'Luminária Quebrada',
                        'abalroamento' => 'Poste Atingido por Veículo',
                    ])
                    ->required(),

                \Filament\Forms\Components\Select::make('prioridade')
                    ->label('Nível de Prioridade')
                    ->options([
                        'baixa' => '🟢 Baixa (Manutenção de Rotina)',
                        'media' => '🟡 Média (Normal)',
                        'alta' => '🔴 Alta (Emergência)',
                    ])
                    ->default('media')
                    ->required(),

                \Filament\Forms\Components\Textarea::make('observacao')
                    ->label('Descrição do Reclamante')
                    ->rows(3),
            ])
            ->action(function (array $data) {
                // No futuro, isso vai salvar na tabela de OS do Módulo de Manutenção
                Notification::make()
                    ->title('Chamado Aberto com Sucesso!')
                    ->body('A equipe técnica foi notificada.')
                    ->success()
                    ->send();
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