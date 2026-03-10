<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CemiterioResource\Pages;
use App\Models\Cemiterio;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class CemiterioResource extends Resource
{
    use HasTenantModule;

    // Define o módulo para o "Porteiro" do sistema bloquear se não assinado
    protected static ?string $tenantModule = 'cemiterio';

    protected static ?string $model = Cemiterio::class;

    // Ícone de lápide / memorial
    protected static ?string $navigationIcon = 'heroicon-o-stop'; 
    protected static ?string $navigationGroup = 'Gestão de Cemitérios';
    protected static ?string $modelLabel = 'Cemitério';
    protected static ?string $pluralModelLabel = 'Cemitérios';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados Principais')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Cemitério')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('address')
                            ->label('Endereço / Localização')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Geometria (Polígono)')
                    ->description('Se você não desenhou no mapa, cole o código GeoJSON gerado pelo QGIS ou AutoCAD abaixo.')
                    ->schema([
                        Forms\Components\Textarea::make('geo_json_input')
                            ->label('Código GeoJSON')
                            ->placeholder('{"type": "Polygon", "coordinates": [[[...]]]}')
                            ->rows(10)
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

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Cemitério')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('area_geo')
                    ->label('Área (m²)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->sortable()
                    ->suffix(' m²'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtros futuros se necessário
            ])
            ->actions([
                // 🛑 Botão Mágico: Voar pro Mapa! Pega o centroide do polígono
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function (Cemiterio $record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        // Como o cemitério é polígono, calculamos o centroide (média de lat/lon) para focar a câmera
                        if ($record->geo_json && isset($record->geo_json->coordinates[0][0][0])) {
                            // Pega o primeiro vértice do polígono só pra mirar a câmera perto
                            $lon = $record->geo_json->coordinates[0][0][0][0]; 
                            $lat = $record->geo_json->coordinates[0][0][0][1];
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=cemiterios&focus_lat=' . $lat . '&focus_lon=' . $lon);
                        }
                        return null;
                    })
                    ->visible(fn (Cemiterio $record) => $record->geo_json !== null),

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
            'index' => Pages\ListCemiterios::route('/'),
            'create' => Pages\CreateCemiterio::route('/create'),
            'edit' => Pages\EditCemiterio::route('/{record}/edit'),
        ];
    }
}