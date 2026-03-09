<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocalEstoqueResource\Pages;
use App\Models\LocalEstoque;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class LocalEstoqueResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'estoque';

    protected static ?string $model = LocalEstoque::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Estoque e Almoxarifado';
    protected static ?string $modelLabel = 'Local de Estoque';
    protected static ?string $pluralModelLabel = 'Locais de Estoque';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome do Local')
                    ->placeholder('Ex: Almoxarifado Central, Caminhão 01...')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->label('Descrição / Observações')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Local')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(50)
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocalEstoques::route('/'),
            'create' => Pages\CreateLocalEstoque::route('/create'),
            'edit' => Pages\EditLocalEstoque::route('/{record}/edit'),
        ];
    }
}