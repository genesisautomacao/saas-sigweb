<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoriaWmsResource\Pages;
use App\Models\CategoriaWms;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoriaWmsResource extends Resource
{
    protected static ?string $model = CategoriaWms::class;

    protected static ?string $tenantRelationshipName = 'categoriasWms';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Administração';

    protected static ?string $modelLabel = 'Categoria WMS';

    protected static ?string $pluralModelLabel = 'Categorias WMS';

    protected static ?int $navigationSort = 90;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')
                ->label('Nome da Categoria (ex.: Cartografia, Ortofoto 2025)')
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('pai_id')
                ->label('Categoria Pai (opcional)')
                ->relationship('pai', 'nome', fn ($query, $record) => $record ? $query->whereKeyNot($record->id) : $query)
                ->searchable()
                ->preload()
                ->helperText('Deixe em branco para uma categoria de primeiro nível.'),

            Forms\Components\TextInput::make('ordem')
                ->label('Ordem de exibição')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Categoria')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('pai.nome')->label('Categoria Pai')->placeholder('— (raiz)')->sortable(),
                Tables\Columns\TextColumn::make('fontes_count')->counts('fontes')->label('Fontes')->badge(),
                Tables\Columns\TextColumn::make('ordem')->label('Ordem')->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCategoriaWms::route('/')];
    }
}
