<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SolicitacaoManutencaoResource\Pages;
use App\Models\SolicitacaoManutencao;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class SolicitacaoManutencaoResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'manutencao';

    protected static ?string $model = SolicitacaoManutencao::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationGroup = 'Manutenção e Serviços';
    protected static ?string $modelLabel = 'Solicitação de Serviço';
    protected static ?string $pluralModelLabel = 'Solicitações (Ocorrências)';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação da Ocorrência')
                    ->schema([

                       // 🛑 O CAMPO POLIMÓRFICO COM MOTOR DE BUSCA CUSTOMIZADO (POSTGRESQL)
                        Forms\Components\MorphToSelect::make('asset')
                            ->label('Busca de Artefato (Por ID, Plaqueta ou Endereço)')
                            ->types([
                                Forms\Components\MorphToSelect\Type::make(\App\Models\Poste::class)
                                    ->titleAttribute('sequential_id')
                                    ->label('💡 Poste de Iluminação')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "Poste #{$record->sequential_id} - Ref: " . ($record->address ?? 'Sem endereço'))
                                    
                                    // 🟢 MÁGICA: Ensina o Filament a buscar no BD pelo Endereço ou ID
                                    ->getSearchResultsUsing(function (string $search) {
                                        $tenantId = \Filament\Facades\Filament::getTenant()->id;
                                        
                                        return \App\Models\Poste::where('tenant_id', $tenantId)
                                            ->where(function ($query) use ($search) {
                                                // Se o usuário digitou um número, busca no ID também
                                                if (is_numeric($search)) {
                                                    $query->where('sequential_id', $search);
                                                }
                                                // Busca no endereço usando 'ilike' (ignora maiúsculas/minúsculas no Postgres)
                                                $query->orWhere('address', 'ilike', "%{$search}%");
                                            })
                                            ->limit(50) // Limita para não travar a tela
                                            ->get()
                                            ->mapWithKeys(fn ($poste) => [$poste->id => "Poste #{$poste->sequential_id} - Ref: " . ($poste->address ?? 'Sem endereço')])
                                            ->toArray();
                                    }),

                                Forms\Components\MorphToSelect\Type::make(\App\Models\Arvore::class)
                                    ->titleAttribute('sequential_id')
                                    ->label('🌳 Árvore / Indivíduo Arbóreo')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "Árvore #{$record->sequential_id} - Ref: " . ($record->address ?? 'Sem endereço'))
                                    
                                    // 🟢 MÁGICA REPLICADA PARA ÁRVORES
                                    ->getSearchResultsUsing(function (string $search) {
                                        $tenantId = \Filament\Facades\Filament::getTenant()->id;
                                        
                                        return \App\Models\Arvore::where('tenant_id', $tenantId)
                                            ->where(function ($query) use ($search) {
                                                if (is_numeric($search)) {
                                                    $query->where('sequential_id', $search);
                                                }
                                                $query->orWhere('address', 'ilike', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn ($arvore) => [$arvore->id => "Árvore #{$arvore->sequential_id} - Ref: " . ($arvore->address ?? 'Sem endereço')])
                                            ->toArray();
                                    }),
                            ])
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('tipo_servico')
                            ->label('Tipo de Ocorrência / Problema')
                            ->options([
                                'Iluminação' => [
                                    'Lâmpada Apagada' => 'Lâmpada Apagada',
                                    'Lâmpada Oscilando' => 'Lâmpada Oscilando',
                                    'Luminária Quebrada' => 'Luminária Quebrada',
                                ],
                                'Arborização' => [
                                    'Poda de Limpeza' => 'Poda de Limpeza',
                                    'Poda por Interferência' => 'Poda por Interferência (Fios/Placas)',
                                    'Remoção' => 'Remoção',
                                    'Tratamento Fitossanitário' => 'Tratamento Fitossanitário',
                                ]
                            ])
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('prioridade')
                            ->label('Nível de Prioridade')
                            ->options([
                                'baixa' => '🟢 Baixa (Rotina)',
                                'media' => '🟡 Média (Normal)',
                                'alta' => '🔴 Alta (Urgência)',
                                'critica' => '⚫ Crítica (Risco de Vida/Dano)',
                            ])
                            ->default('media')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status do Chamado')
                            ->options([
                                'pendente' => 'Pendente (Aguardando Triagem)',
                                'analise' => 'Em Análise',
                                'aprovada_os' => 'Aprovado (OS Gerada)',
                                'rejeitada' => 'Rejeitada / Improcedente',
                            ])
                            ->default('pendente')
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Detalhes e Evidências')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            // Novo campo de Pessoa Cadastrada (Mostra CPF)
                            Forms\Components\Select::make('pessoa_id')
                                ->label('Reclamante Cadastrado (Módulo Pessoas)')
                                ->relationship('pessoa', 'name')
                                ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name} (Doc: {$record->cpf})")
                                ->searchable()
                                ->preload(),

                            Forms\Components\TextInput::make('solicitante_nome')
                                ->label('Reclamante Avulso / Anônimo')
                                ->placeholder('Use apenas se não houver cadastro...'),
                        ]),

                        Forms\Components\Textarea::make('observacao')
                            ->label('Descrição do Problema')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('foto_ocorrencia')
                            ->label('Foto do Problema (Se houver)')
                            ->image()
                            ->directory('solicitacoes_fotos')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('Chamado #')
                    ->sortable()
                    ->searchable(),

                // 🛑 EXIBINDO O POLIMORFISMO NA TABELA 🛑
                Tables\Columns\TextColumn::make('asset_type')
                    ->label('Origem')
                    ->formatStateUsing(function (string $state, $record) {
                        $tipo = class_basename($state);
                        return $tipo === 'Poste' ? "💡 Poste #{$record->asset->sequential_id}" : "🌳 Árvore #{$record->asset->sequential_id}";
                    })
                    ->badge()
                    ->color(fn($state) => str_contains($state, 'Poste') ? 'warning' : 'success'),

                Tables\Columns\TextColumn::make('tipo_servico')
                    ->label('Serviço Requisitado')
                    ->searchable(),

                Tables\Columns\TextColumn::make('prioridade')
                    ->label('Prioridade')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'baixa' => 'success',
                        'media' => 'warning',
                        'alta' => 'danger',
                        'critica' => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pendente' => 'danger',
                        'analise' => 'warning',
                        'aprovada_os' => 'success',
                        'rejeitada' => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => strtoupper($state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aberto em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pendente' => 'Pendente',
                        'analise' => 'Em Análise',
                        'aprovada_os' => 'Aprovado (OS Gerada)',
                        'rejeitada' => 'Rejeitada',
                    ]),
                Tables\Filters\SelectFilter::make('prioridade')
                    ->options([
                        'baixa' => 'Baixa',
                        'media' => 'Média',
                        'alta' => 'Alta',
                        'critica' => 'Crítica',
                    ]),
            ])
           ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    
                   // 🛑 BOTÃO 1: GERAR OS (Só aparece se NÃO tiver virado OS ainda)
                    Tables\Actions\Action::make('gerar_os')
                        ->label('Gerar Ordem de Serviço')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('success')
                        ->visible(fn ($record) => $record->status !== 'aprovada_os')
                        ->url(fn ($record) => OrdemServicoResource::getUrl('create', ['solicitacao_id' => $record->id])),

                    // 🟢 BOTÃO 2: VER OS GERADA (Só aparece quando JÁ TEM OS)
                    Tables\Actions\Action::make('ver_os')
                        ->label('Ver Ordem Gerada')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->visible(fn ($record) => $record->status === 'aprovada_os')
                        ->url(function ($record) {
                            // Busca a primeira OS que tenha nascido dessa solicitação
                            $os = \App\Models\OrdemServico::where('solicitacao_id', $record->id)->first();
                            // Se achou, manda direto para a tela de edição da OS!
                            return $os ? OrdemServicoResource::getUrl('edit', ['record' => $os->id]) : null;
                        }),

                    Tables\Actions\DeleteAction::make(),
                ])->tooltip('Ações / Opções'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSolicitacaoManutencaos::route('/'),
            'create' => Pages\CreateSolicitacaoManutencao::route('/create'),
            'edit' => Pages\EditSolicitacaoManutencao::route('/{record}/edit'),
        ];
    }
}