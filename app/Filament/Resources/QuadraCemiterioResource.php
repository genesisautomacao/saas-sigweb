<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuadraCemiterioResource\Pages;
use App\Models\QuadraCemiterio;
use App\Models\Cemiterio;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class QuadraCemiterioResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'cemiterio';

    protected static ?string $model = QuadraCemiterio::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Gestão de Cemitérios';
    protected static ?string $modelLabel = 'Quadra (Cemitério)';
    protected static ?string $pluralModelLabel = 'Quadras';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados Principais')
                    ->schema([
                        Forms\Components\Select::make('cemiterio_id')
                            ->label('Cemitério')
                            ->options(fn() => Cemiterio::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('name')
                            ->label('Identificação da Quadra (Ex: Quadra A, Quadra 01)')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Geometria (Polígono)')
                    ->description('Se você não desenhou no mapa, cole o código GeoJSON gerado pelo QGIS ou AutoCAD abaixo.')
                    ->schema([
                        Forms\Components\Textarea::make('geo_json_input')
                            ->label('Código GeoJSON')
                            ->placeholder('{"type": "Polygon", "coordinates": [[[...]]]}')
                            ->rows(8)
                            ->columnSpanFull()
                            ->helperText('Atenção: Apenas modifique este campo se souber o que está fazendo. Certifique-se que as coordenadas estão em EPSG:4326 (WGS84).'),
                    ])
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

                Tables\Columns\TextColumn::make('cemiterio.name')
                    ->label('Cemitério')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Quadra')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('area_geo')
                    ->label('Área (m²)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->sortable()
                    ->suffix(' m²'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('cemiterio_id')
                    ->label('Filtrar por Cemitério')
                    ->relationship('cemiterio', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function (QuadraCemiterio $record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if ($record->geo_json && isset($record->geo_json->coordinates[0][0][0])) {
                            $lon = $record->geo_json->coordinates[0][0][0][0]; 
                            $lat = $record->geo_json->coordinates[0][0][0][1];
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=quadras_cemiterio&focus_lat=' . $lat . '&focus_lon=' . $lon);
                        }
                        return null;
                    })
                    ->visible(fn (QuadraCemiterio $record) => $record->geo_json !== null),

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
            'index' => Pages\ListQuadraCemiterios::route('/'),
            'create' => Pages\CreateQuadraCemiterio::route('/create'),
            'edit' => Pages\EditQuadraCemiterio::route('/{record}/edit'),
        ];
    }
}