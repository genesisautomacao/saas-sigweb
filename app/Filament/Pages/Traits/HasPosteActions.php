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
                // 1. Prepara os dados básicos
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['code'] = (string) Str::uuid();

                // 2. Separa os IDs dos logradouros (pois é um relacionamento N:N e não vai direto na tabela postes)
                $logradourosIds = $data['logradouros'] ?? [];
                unset($data['logradouros']);

                // 3. Salva o Poste (incluindo o campo 'address' se preenchido)
                $poste = Poste::create($data);

                // 4. Salva o Relacionamento na tabela pivô (poste_logradouro)
                if (!empty($logradourosIds)) {
                    $poste->logradouros()->sync($logradourosIds);
                }

                Notification::make()->title('Poste Criado com Sucesso!')->success()->send();

                // Limpa e atualiza
                $this->geometriaRascunho = null;
                $this->dispatch('limpar-rascunho-mapa');
                $this->dispatch('atualizar-camada-postes');
            });
    }

    /**
     * Ação: Opções e Edição Completa do Poste
     */
    public function opcoesPosteAction(): Action
    {
        return Action::make('opcoesPoste')
            ->model(\App\Models\Poste::class)
            ->hiddenLabel()
            ->modalHeading(function () {
                $poste = Poste::find($this->posteAtivoId);
                return 'Editar Ponto de Iluminação #' . ($poste ? $poste->sequential_id : $this->posteAtivoId);
            })
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->fillForm(function (): array {
                // Carrega o poste já trazendo os logradouros vinculados
                $poste = Poste::with('logradouros')->find($this->posteAtivoId);
                if (!$poste)
                    return [];

                return [
                    'logradouros' => $poste->logradouros->pluck('id')->toArray(), // Extrai os IDs para o Select Múltiplo
                    'address' => $poste->address, // Referência
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
                    // Separa os logradouros antes do update
                    $logradourosIds = $data['logradouros'] ?? [];
                    unset($data['logradouros']);

                    // Atualiza os dados principais (incluindo 'address')
                    $poste->update($data);

                    // Sincroniza a tabela pivô
                    $poste->logradouros()->sync($logradourosIds);

                    Notification::make()->title('Poste Atualizado!')->success()->send();
                    $this->dispatch('atualizar-camada-postes');
                }
            })
            ->extraModalFooterActions([
                Action::make('editar_geometria')
                    ->label('Geometria')
                    ->color('warning')
                    ->icon('heroicon-o-map')
                    ->action(function () {
                        $this->dispatch('iniciar-edicao-geometria-poste', id: $this->posteAtivoId);
                        $this->dispatch('fechar-modal-filament');
                    }),

                Action::make('excluir_poste')
                    ->label('Excluir')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
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
            ]);
    }

    /**
     * Helper: Centraliza os campos do formulário
     */
    protected function getPosteFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Section::make('Localização e Endereçamento')
                ->schema([
                    \Filament\Forms\Components\Select::make('logradouros')
                        ->label('Logradouro(s) Vinculado(s)')
                        // 🛑 A SOLUÇÃO: Carregamos as opções na mão e tiramos o relacionamento automático
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
                            // MÁGICA: Preenche o logradouro sozinho na hora de ABRIR o modal de criação!
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