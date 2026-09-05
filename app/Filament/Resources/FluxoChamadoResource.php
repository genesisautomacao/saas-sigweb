<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FluxoChamadoResource\Pages;
use App\Filament\Resources\FluxoChamadoResource\RelationManagers\FasesRelationManager;
use App\Models\FluxoChamado;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FluxoChamadoResource extends Resource
{
    use \App\Traits\HasTenantModule;

    /** App de Chamados faz parte do módulo Coleta Cadastral (D4 — docs/Modulos_Permissoes.txt) */
    protected static ?string $tenantModule = 'chamados'; // D8 (2026-09-05): módulo próprio

    protected static ?string $model = FluxoChamado::class;

    protected static ?string $tenantRelationshipName = 'fluxosChamado';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationGroup = 'App de Chamados';

    protected static ?string $modelLabel = 'Fluxo de Trabalho';

    protected static ?string $pluralModelLabel = 'Fluxos de Trabalho';

    protected static ?int $navigationSort = 31;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Fluxo de Trabalho')->schema([
                Forms\Components\TextInput::make('nome')
                    ->label('Nome do Fluxo (ex.: Reclamação de Iluminação Pública)')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('categoria_chamado_id')
                    ->label('Categoria')
                    ->relationship('categoria', 'nome')
                    ->searchable()
                    ->preload()
                    ->helperText('Vincula este fluxo a uma categoria (item 158).'),

                Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
                Forms\Components\TextInput::make('ordem')->label('Ordem')->numeric()->default(0),
            ])->columns(2),

            Forms\Components\Section::make('Boletim / Questionário (respondido pelo cidadão no app — item 154)')
                ->description('Monte as perguntas que o cidadão responderá ao abrir o chamado deste fluxo.')
                ->schema([
                    Forms\Components\Builder::make('boletim')
                        ->hiddenLabel()
                        ->blocks(self::boletimBlocks())
                        ->addActionLabel('Adicionar Campo')
                        ->collapsible(),
                ])->collapsible(),
        ]);
    }

    /** 4 tipos de campo do edital (item 154/125): texto, checkbox, mapa, CPF/telefone. */
    public static function boletimBlocks(): array
    {
        return [
            Forms\Components\Builder\Block::make('texto')
                ->label('Campo de Texto Simples')
                ->icon('heroicon-m-bars-3-bottom-left')
                ->schema([
                    Forms\Components\TextInput::make('label_campo')->label('Título do Campo')->required(),
                    Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório?')->default(false),
                ]),

            Forms\Components\Builder\Block::make('checkbox')
                ->label('Múltipla Escolha (Checkbox)')
                ->icon('heroicon-m-list-bullet')
                ->schema([
                    Forms\Components\TextInput::make('label_campo')->label('Pergunta')->required(),
                    Forms\Components\TagsInput::make('opcoes')->label('Opções (Enter para separar)')->required(),
                    Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório?')->default(false),
                ]),

            Forms\Components\Builder\Block::make('mapa')
                ->label('Seleção de Posição no Mapa')
                ->icon('heroicon-m-map-pin')
                ->schema([
                    Forms\Components\TextInput::make('label_campo')->label('Instrução (ex.: Marque o local do problema)')->required(),
                    Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório?')->default(false),
                ]),

            Forms\Components\Builder\Block::make('documento')
                ->label('Campo com Máscara (CPF/Telefone)')
                ->icon('heroicon-m-identification')
                ->schema([
                    Forms\Components\TextInput::make('label_campo')->label('Título do Campo')->required(),
                    Forms\Components\Select::make('mascara')->label('Tipo de Máscara')
                        ->options(['cpf' => 'CPF', 'telefone' => 'Telefone / Celular'])->required(),
                    Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório?')->default(false),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Fluxo')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('categoria.nome')->label('Categoria')->badge()->placeholder('—'),
                Tables\Columns\TextColumn::make('fases_count')->counts('fases')->label('Fases')->badge(),
                Tables\Columns\IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [FasesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFluxoChamados::route('/'),
            'create' => Pages\CreateFluxoChamado::route('/create'),
            'edit' => Pages\EditFluxoChamado::route('/{record}/edit'),
        ];
    }
}
