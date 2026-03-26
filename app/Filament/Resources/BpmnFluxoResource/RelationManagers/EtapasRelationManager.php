<?php

namespace App\Filament\Resources\BpmnFluxoResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EtapasRelationManager extends RelationManager
{
    protected static string $relationship = 'etapas';
    protected static ?string $recordTitleAttribute = 'nome';
    protected static ?string $title = 'Configuração das Etapas (Regras e Formulários)';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Configurações')
                    ->tabs([
                        // ABA 1: REGRAS E MAPA
                        Forms\Components\Tabs\Tab::make('Regras e Mapa')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\TextInput::make('nome')
                                    ->label('Nome da Etapa (Ex: Análise Técnica)')
                                    ->required()
                                    ->maxLength(255),
                                    
                                Forms\Components\ColorPicker::make('cor_mapa')
                                    ->label('Cor do Lote no Mapa')
                                    ->required()
                                    ->default('#f59e0b'), // Default Amber/Amarelo
                                    
                                Forms\Components\TextInput::make('tempo_medio_minutos')
                                    ->label('SLA / Tempo Médio (Minutos)')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Tempo previsto para conclusão desta fase.'),
                                    
                                Forms\Components\Select::make('perfis_autorizados')
                                    ->label('Perfis Autorizados (Quem pode analisar?)')
                                    ->multiple()
                                    ->options([
                                        'analista' => 'Analistas Técnicos',
                                        'fiscal' => 'Fiscais de Campo',
                                        'procuradoria' => 'Procuradoria Jurídica',
                                        'secretario' => 'Secretário / Prefeito',
                                    ]), // (Mais tarde podemos atrelar aos Cargos reais do banco)
                            ])->columns(2),

                        // ABA 2: O CONSTRUTOR DE FORMULÁRIO DO EDITAL
                        Forms\Components\Tabs\Tab::make('Formulário da Etapa')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\Builder::make('campos_formulario')
                                    ->label('Monte o formulário que será exigido nesta etapa:')
                                    ->blocks([
                                        // 1. TEXTO SIMPLES
                                        Forms\Components\Builder\Block::make('texto')
                                            ->label('Campo de Texto Simples')
                                            ->icon('heroicon-m-bars-3-bottom-left')
                                            ->schema([
                                                Forms\Components\TextInput::make('label_campo')->label('Título do Campo')->required(),
                                                Forms\Components\Toggle::make('obrigatorio')->label('Preenchimento Obrigatório?')->default(false),
                                            ]),
                                            
                                        // 2. CHECKBOX
                                        Forms\Components\Builder\Block::make('checkbox')
                                            ->label('Múltipla Escolha (Checkbox)')
                                            ->icon('heroicon-m-list-bullet')
                                            ->schema([
                                                Forms\Components\TextInput::make('label_campo')->label('Pergunta')->required(),
                                                Forms\Components\TagsInput::make('opcoes')->label('Opções (Aperte Enter para separar)')->required(),
                                                Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório?')->default(false),
                                            ]),
                                            
                                        // 3. MAPA (COORDENADA)
                                        Forms\Components\Builder\Block::make('mapa')
                                            ->label('Seleção de Posição no Mapa')
                                            ->icon('heroicon-m-map-pin')
                                            ->schema([
                                                Forms\Components\TextInput::make('label_campo')->label('Instrução (Ex: Marque o poste com defeito)')->required(),
                                                Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório?')->default(false),
                                            ]),
                                            
                                        // 4. MÁSCARAS (CPF/TELEFONE)
                                        Forms\Components\Builder\Block::make('documento')
                                            ->label('Campo com Máscara (CPF/Tel)')
                                            ->icon('heroicon-m-identification')
                                            ->schema([
                                                Forms\Components\TextInput::make('label_campo')->label('Título do Campo')->required(),
                                                Forms\Components\Select::make('mascara')
                                                    ->label('Tipo de Máscara')
                                                    ->options([
                                                        'cpf' => 'CPF',
                                                        'telefone' => 'Telefone / Celular',
                                                    ])->required(),
                                                Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório?')->default(false),
                                            ]),
                                    ])
                                    ->addActionLabel('Adicionar Novo Campo')
                                    ->collapsible(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('cor_mapa')->label('Cor'),
                Tables\Columns\TextColumn::make('nome')->label('Etapa')->weight('bold'),
                Tables\Columns\TextColumn::make('tempo_medio_minutos')->label('SLA (min)'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = \Filament\Facades\Filament::getTenant()->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}