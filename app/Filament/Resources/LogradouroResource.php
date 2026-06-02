<?php
namespace App\Filament\Resources;
use App\Models\Logradouro;
use App\Filament\Resources\LogradouroResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Forms\Form;
use App\Traits\HasTenantModule;

class LogradouroResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = Logradouro::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Módulo Imobiliário';
     protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Logradouro')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Logradouro')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('extensao_geo')
                            ->label('Extensão (m)')
                            ->helperText('Calculada automaticamente da geometria.')
                            ->disabled()
                            ->dehydrated(false)
                            ->numeric()
                            ->suffix('m'),

                        // O json de importação / ajuste topográfico
                        Forms\Components\Textarea::make('geo_json_input')
                            ->label('Coordenadas da Rua (Ou GeoJSON LineString)')
                            ->rows(10)
                            ->columnSpanFull()
                            ->helperText('O sistema desenha automaticamente no mapa, mas você pode colar um GeoJSON completo OU uma lista simples de coordenadas do traçado (ex: "-50.404263 -26.972014, -50.401214 -26.974058...")'),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nome do Logradouro')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('extensao_geo')
                    ->label('Extensão (m)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->suffix(' m')
                    ->sortable()
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function (Logradouro $record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        $lon = null;
                        $lat = null;

                        // Verifica a profundidade exata dependendo se o banco devolveu MultiLineString ou LineString
                        if ($record->geo_json && isset($record->geo_json->coordinates)) {
                            $tipo = $record->geo_json->type ?? '';
                            
                            if ($tipo === 'MultiLineString' && isset($record->geo_json->coordinates[0][0])) {
                                $lon = $record->geo_json->coordinates[0][0][0] ?? null;
                                $lat = $record->geo_json->coordinates[0][0][1] ?? null;
                            } elseif ($tipo === 'LineString' && isset($record->geo_json->coordinates[0])) {
                                $lon = $record->geo_json->coordinates[0][0] ?? null;
                                $lat = $record->geo_json->coordinates[1] ?? null;
                            }
                        }

                        if ($lon && $lat) {
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=logradouros&focus_lat=' . $lat . '&focus_lon=' . $lon);
                        }
                        return url('/app/' . $tenant->slug . '/mapa-interativo');
                    })
                    ->visible(fn (Logradouro $record) => $record->geo_json !== null),
                    
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLogradouros::route('/'),
            'edit' => Pages\EditLogradouro::route('/{record}/edit'),
            'create' => Pages\CreateLogradouro::route('/create'),
        ];
    }
}