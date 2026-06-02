<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerimetroUrbanoResource\Pages;
use App\Models\PerimetroUrbano;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PerimetroUrbanoResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = PerimetroUrbano::class;
    protected static ?string $navigationIcon = 'heroicon-o-globe-americas';
    protected static ?string $navigationGroup = 'Módulo Imobiliário';
    protected static ?int $navigationSort = -10;
    protected static ?string $navigationLabel = 'Distritos / Limites';
    protected static ?string $modelLabel = 'Distrito / Limite';
    protected static ?string $pluralModelLabel = 'Distritos / Limites';
    protected static ?string $slug = 'distritos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('distrito')
                    ->label('Distrito (opcional)')
                    ->helperText('Use para classificar o registro como Distrito, Limite Municipal, Perímetro Urbano etc.')
                    ->maxLength(255),

                Forms\Components\TextInput::make('area_geo')
                    ->label('Área (m²)')
                    ->helperText('Calculada automaticamente da geometria.')
                    ->disabled()
                    ->dehydrated(false)
                    ->numeric()
                    ->suffix('m²'),

            ])->columns(2),

            Forms\Components\Section::make('Dados Espaciais')->schema([
                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenadas do Perímetro (Ou GeoJSON)')
                    ->helperText('Você pode colar um GeoJSON completo OU apenas uma lista de coordenadas no formato: "-50.404263 -26.972014, -50.401214 -26.974058..."')
                    ->rows(15)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('distrito')->label('Distrito')->searchable(),
                Tables\Columns\TextColumn::make('area_geo')
                    ->label('Área (m²)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->suffix(' m²')
                    ->sortable()
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function ($record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        // Centroide preciso via PostGIS — funciona para Polygon e MultiPolygon
                        $row = \Illuminate\Support\Facades\DB::table('perimetros_urbanos')
                            ->selectRaw('ST_X(ST_Centroid(geo)) AS lon, ST_Y(ST_Centroid(geo)) AS lat')
                            ->where('id', $record->id)
                            ->first();
                        if ($row && $row->lat && $row->lon) {
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=perimetros&focus_lat=' . $row->lat . '&focus_lon=' . $row->lon . '&zoom=13');
                        }
                        return null;
                    })
                    ->visible(fn($record) => $record->geo_json !== null),
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
            'index'  => Pages\ListPerimetrosUrbanos::route('/'),
            'create' => Pages\CreatePerimetroUrbano::route('/create'),
            'edit'   => Pages\EditPerimetroUrbano::route('/{record}/edit'),
        ];
    }
}
