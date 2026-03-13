<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoteResource\Pages;
use App\Models\Lote;
use App\Models\Quadra;
use App\Models\Zona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class LoteResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'imobiliario'; // O módulo agora é outro!

    protected static ?string $model = Lote::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Módulo Imobiliário';
    protected static ?string $modelLabel = 'Lote (Terreno)';
    protected static ?string $pluralModelLabel = 'Lotes';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Localização e Identificação')
                    ->schema([

                        Forms\Components\TextInput::make('numero_lote')
                            ->label('Número do Lote (Planta)')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('main_facade_length')
                            ->label('Testada Principal / Frente (metros)')
                            ->numeric()
                            ->suffix('m'),

                        Forms\Components\TextInput::make('area_geo')
                            ->label('Área do Lote (m²)')
                            ->numeric()
                            ->suffix('m²'),

                        Forms\Components\Select::make('quadra_id')
                            ->label('Quadra Pertencente')
                            ->options(fn() => Quadra::pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('zona_id')
                            ->label('Zona Urbana (Para Viabilidade)')
                            ->options(fn() => Zona::pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                    ])->columns(3),

                Forms\Components\Section::make('Geometria do Lote (Polígono)')
                    ->description('Caso não desenhado no mapa, insira o GeoJSON gerado pela topografia.')
                    ->schema([
                        Forms\Components\Textarea::make('geo_json_input')
                            ->label('Código GeoJSON')
                            ->placeholder('{"type": "Polygon", "coordinates": [[[...]]]}')
                            ->rows(6)
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('numero_lote')
                    ->label('Lote nº')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('unidadesImobiliarias.codigo_imovel_tributario')
                    ->label('Códigos Fiscais (Unidades)')
                    ->badge() // Transforma os códigos em "etiquetas" visuais
                    ->color('success') // Cor verdinha pra destacar
                    ->searchable() // <-- A MÁGICA DA PESQUISA ESTÁ AQUI!
                    ->default('Nenhum'),

                Tables\Columns\TextColumn::make('quadra.name')
                    ->label('Quadra')
                    ->sortable(),

                Tables\Columns\TextColumn::make('zona.sigla')
                    ->label('Zona')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('area_geo')
                    ->label('Área do Terreno')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->suffix(' m²')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('quadra_id')
                    ->label('Filtrar por Quadra')
                    ->relationship('quadra', 'name')
                    ->multiple()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('zona_id')
                    ->label('Filtrar por Zona')
                    ->relationship('zona', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function (Lote $record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if ($record->geo_json && isset($record->geo_json->coordinates[0][0][0])) {
                            $lon = $record->geo_json->coordinates[0][0][0][0];
                            $lat = $record->geo_json->coordinates[0][0][0][1];
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=lotes&focus_lat=' . $lat . '&focus_lon=' . $lon);
                        }
                        return null;
                    })
                    ->visible(fn(Lote $record) => $record->geo_json !== null),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            LoteResource\RelationManagers\UnidadesImobiliariasRelationManager::class,
            LoteResource\RelationManagers\EdificacoesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLotes::route('/'),
            'create' => Pages\CreateLote::route('/create'),
            'edit' => Pages\EditLote::route('/{record}/edit'),
        ];
    }
}