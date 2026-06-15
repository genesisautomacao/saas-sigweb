<?php

namespace App\Filament\Resources;

use App\Models\AreaReurb;
use App\Filament\Resources\AreaReurbResource\Pages;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class AreaReurbResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = AreaReurb::class;
    protected static ?string $navigationIcon = 'heroicon-o-home-modern';
    protected static ?string $navigationGroup = 'Módulo Imobiliário';
    protected static ?int $navigationSort = 5;
    protected static ?string $modelLabel = 'Área REURB';
    protected static ?string $pluralModelLabel = 'Áreas REURB';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')->schema([
                Forms\Components\TextInput::make('nome')
                    ->label('Nome / Identificação da Área')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('tipo_reurb')
                    ->label('Tipo REURB')
                    ->options([
                        'Reurb-S' => 'Reurb-S — Social',
                        'Reurb-E' => 'Reurb-E — Específico',
                        'Sem Classificação' => 'Sem Classificação',
                    ])
                    ->default('Sem Classificação')
                    ->required(),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'em_analise' => 'Em Análise',
                        'regularizado' => 'Regularizado',
                        'arquivado' => 'Arquivado',
                    ])
                    ->default('em_analise')
                    ->required(),

                Forms\Components\TextInput::make('area_geo')
                    ->label('Área (m²)')
                    ->helperText('Calculada automaticamente da geometria.')
                    ->disabled()
                    ->dehydrated(false)
                    ->numeric()
                    ->suffix('m²'),
            ])->columns(4),

            Forms\Components\Section::make('Observações')->schema([
                Forms\Components\Textarea::make('observacao')
                    ->label('Observação')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Delimitação Geográfica')->schema([
                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenadas da Área (GeoJSON)')
                    ->helperText('Cole um GeoJSON do tipo Polygon ou MultiPolygon.')
                    ->rows(10)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('tipo_reurb')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Reurb-S' => 'warning',
                        'Reurb-E' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'em_analise' => 'Em Análise',
                        'regularizado' => 'Regularizado',
                        'arquivado' => 'Arquivado',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'em_analise' => 'info',
                        'regularizado' => 'success',
                        'arquivado' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('area_geo')
                    ->label('Área (m²)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->suffix(' m²')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sequential_id', 'asc')
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
            'index' => Pages\ListAreasReurb::route('/'),
            'create' => Pages\CreateAreaReurb::route('/create'),
            'edit' => Pages\EditAreaReurb::route('/{record}/edit'),
        ];
    }
}
