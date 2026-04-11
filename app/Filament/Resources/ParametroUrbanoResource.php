<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParametroUrbanoResource\Pages;
use App\Models\ParametroUrbano;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class ParametroUrbanoResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = ParametroUrbano::class;
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'Consultas de Viabilidade';
    protected static ?string $modelLabel = 'Parâmetro de Loteamento';
    protected static ?string $pluralModelLabel = 'Parâmetros de Loteamento';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Vínculo')->schema([
                Forms\Components\Select::make('zona_id')
                    ->label('Zona de Uso')
                    ->relationship('zona', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->sigla} - {$record->name}")
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(ignoreRecord: true), // Garante que cada zona só tenha 1 parâmetro
            ]),

            Forms\Components\Section::make('Regras Geométricas (Metragem)')->schema([
                Forms\Components\TextInput::make('area_minima')
                    ->label('Área Mínima (m²)')
                    ->numeric()
                    ->inputMode('decimal')
                    ->suffix('m²'),
                
                Forms\Components\TextInput::make('area_maxima')
                    ->label('Área Máxima (m²)')
                    ->numeric()
                    ->inputMode('decimal')
                    ->suffix('m²'),
                
                Forms\Components\TextInput::make('testada_minima')
                    ->label('Testada/Face Mínima (m)')
                    ->numeric()
                    ->inputMode('decimal')
                    ->suffix('m'),
                
                Forms\Components\TextInput::make('testada_maxima')
                    ->label('Testada/Face Máxima (m)')
                    ->numeric()
                    ->inputMode('decimal')
                    ->suffix('m'),
            ])->columns(2)
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('zona.sigla')
                    ->label('Zona')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('area_minima')
                    ->label('Área Mín.')
                    ->suffix(' m²')
                    ->sortable(),

                Tables\Columns\TextColumn::make('area_maxima')
                    ->label('Área Máx.')
                    ->suffix(' m²')
                    ->sortable(),

                Tables\Columns\TextColumn::make('testada_minima')
                    ->label('Face Mín.')
                    ->suffix(' m')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListParametroUrbanos::route('/'),
            'create' => Pages\CreateParametroUrbano::route('/create'),
            'edit' => Pages\EditParametroUrbano::route('/{record}/edit'),
        ];
    }
}