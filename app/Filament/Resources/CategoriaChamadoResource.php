<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoriaChamadoResource\Pages;
use App\Models\CategoriaChamado;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoriaChamadoResource extends Resource
{
    use \App\Traits\HasTenantModule;

    /** App de Chamados faz parte do módulo Coleta Cadastral (D4 — docs/Modulos_Permissoes.txt) */
    protected static ?string $tenantModule = 'chamados'; // D8 (2026-09-05): módulo próprio

    protected static ?string $model = CategoriaChamado::class;

    protected static ?string $tenantRelationshipName = 'categoriasChamado';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'App de Chamados';

    protected static ?string $modelLabel = 'Categoria de Chamado';

    protected static ?string $pluralModelLabel = 'Categorias de Chamado';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')
                ->label('Nome da Categoria (ex.: Iluminação, Vias, Limpeza)')
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('pai_id')
                ->label('Categoria Pai (opcional)')
                ->relationship('pai', 'nome', fn ($query, $record) => $record ? $query->whereKeyNot($record->id) : $query)
                ->searchable()
                ->preload()
                ->helperText('Deixe em branco para uma categoria de primeiro nível.'),

            Forms\Components\ColorPicker::make('cor')->label('Cor')->default('#3b82f6'),

            Forms\Components\FileUpload::make('icone')
                ->label('Ícone (PNG ou JPG)')
                ->image()
                ->acceptedFileTypes(['image/png', 'image/jpeg'])
                ->directory('chamados/icones')
                ->maxSize(1024),

            Forms\Components\Toggle::make('privada')
                ->label('Categoria Privada (visível só para fiscais da Prefeitura)')
                ->default(false),

            Forms\Components\TextInput::make('ordem')->label('Ordem de exibição')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                Tables\Columns\ImageColumn::make('icone')->label('Ícone')->circular(),
                Tables\Columns\ColorColumn::make('cor')->label('Cor'),
                Tables\Columns\TextColumn::make('nome')->label('Categoria')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('pai.nome')->label('Categoria Pai')->placeholder('— (raiz)')->sortable(),
                Tables\Columns\IconColumn::make('privada')->label('Privada')->boolean(),
                Tables\Columns\TextColumn::make('fluxos_count')->counts('fluxos')->label('Fluxos')->badge(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCategoriaChamados::route('/')];
    }
}
