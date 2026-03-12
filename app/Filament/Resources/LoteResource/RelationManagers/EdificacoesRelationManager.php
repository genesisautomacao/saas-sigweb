<?php

namespace App\Filament\Resources\LoteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EdificacoesRelationManager extends RelationManager
{
    protected static string $relationship = 'edificacoes';
    protected static ?string $title = 'Edificações (Construções)';
    protected static ?string $icon = 'heroicon-o-home-modern';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('tipo')
                    ->label('Tipo')
                    ->options(['Casa' => 'Casa', 'Predio' => 'Prédio', 'Galpao' => 'Galpão'])
                    ->required(),
                Forms\Components\TextInput::make('area_geo')
                    ->label('Área (m²)')
                    ->numeric()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID'),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge(),
                Tables\Columns\TextColumn::make('area_geo')
                    ->label('Área Construída')
                    ->suffix(' m²')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Nova Edificação'),
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
}