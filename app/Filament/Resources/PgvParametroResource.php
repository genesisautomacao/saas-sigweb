<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PgvParametroResource\Pages;
use App\Models\PgvParametro;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class PgvParametroResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'pgv';

    protected static ?string $model = PgvParametro::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $modelLabel = 'Parâmetro Base (PGV)';
    protected static ?string $pluralModelLabel = 'Parâmetros Base (PGV)';
    protected static ?string $navigationGroup = 'Gestão Tributária (PGV)';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Definição de Valores do Metro Quadrado')
                    ->description('Estes valores servirão de base para o cálculo do Valor Venal dos lotes e edificações.')
                    ->schema([
                        Forms\Components\TextInput::make('nome_padrao')
                            ->label('Nome do Padrão')
                            ->placeholder('Ex: Setor Comercial A, Periferia B...')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('valor_m2_terreno')
                            ->label('Valor m² do Terreno')
                            ->required()
                            ->numeric()
                            ->prefix('R$')
                            ->default(0.00),

                        Forms\Components\TextInput::make('valor_m2_edificacao')
                            ->label('Valor m² da Edificação')
                            ->required()
                            ->numeric()
                            ->prefix('R$')
                            ->default(0.00),
                    ])->columns(2),

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

                Tables\Columns\TextColumn::make('nome_padrao')
                    ->label('Nome do Padrão')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('valor_m2_terreno')
                    ->label('m² Terreno')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('valor_m2_edificacao')
                    ->label('m² Edificação')
                    ->money('BRL')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPgvParametros::route('/'),
            'create' => Pages\CreatePgvParametro::route('/create'),
            'edit' => Pages\EditPgvParametro::route('/{record}/edit'),
        ];
    }
}
