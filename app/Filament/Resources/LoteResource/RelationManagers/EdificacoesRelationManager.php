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
                    ->label('Finalidade / Uso')
                    ->options([
                        'Residencial' => 'Residencial',
                        'Comercial'   => 'Comercial',
                        'Industrial'  => 'Industrial',
                        'Misto'       => 'Misto',
                        'Outro'       => 'Outro',
                    ])
                    ->required(),
                Forms\Components\Select::make('tp_construcao')
                    ->label('Tipo de Construção (material)')
                    ->options([
                        'Alvenaria' => 'Alvenaria',
                        'Madeira'   => 'Madeira',
                        'Mista'     => 'Mista',
                        'Outro'     => 'Outro',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('caracteristica_construcao')
                    ->label('Característica da Construção')
                    ->placeholder('Ex: Pavimento 1, Anexo, Edícula...')
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\Select::make('estado_conservacao')
                    ->label('Estado de Conservação')
                    ->options([
                        'Ruim'    => 'Ruim',
                        'Regular' => 'Regular',
                        'Médio'   => 'Médio',
                        'Bom'     => 'Bom',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('pavimento')
                    ->label('Nº de Pavimentos')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(99)
                    ->nullable(),
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
                Tables\Columns\TextColumn::make('tipo')->label('Finalidade')->badge()->color('info'),
                Tables\Columns\TextColumn::make('tp_construcao')->label('Construção')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('estado_conservacao')->label('Conservação')->badge()
                    ->color(fn ($state) => match ($state) {
                        'Bom'     => 'success',
                        'Médio'   => 'warning',
                        'Regular' => 'warning',
                        'Ruim'    => 'danger',
                        default   => 'gray',
                    }),
                Tables\Columns\TextColumn::make('pavimento')->label('Pavimentos')->alignCenter(),
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