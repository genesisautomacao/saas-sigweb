<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogradouroCemiterioResource\Pages;
use App\Models\LogradouroCemiterio;
use App\Models\Cemiterio;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class LogradouroCemiterioResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'cemiterio';

    protected static ?string $model = LogradouroCemiterio::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Gestão de Cemitérios';
    protected static ?string $modelLabel = 'Rua/Viela (Cemitério)';
    protected static ?string $pluralModelLabel = 'Ruas e Vielas';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados Principais')
                    ->schema([
                        Forms\Components\Select::make('cemiterio_id')
                            ->label('Pertence a qual Cemitério?')
                            ->options(fn() => Cemiterio::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nome da Rua, Viela ou Caminho')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Geometria (Linha)')
                    ->description('Se você não desenhou no mapa, cole o código GeoJSON (LineString) gerado pelo QGIS ou AutoCAD abaixo.')
                    ->schema([
                        Forms\Components\Textarea::make('geo_json_input')
                            ->label('Código GeoJSON')
                            ->placeholder('{"type": "LineString", "coordinates": [[...]]}')
                            ->rows(8)
                            ->columnSpanFull()
                            ->helperText('Atenção: Apenas modifique este campo se souber o que está fazendo.'),
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
                    ->label('Nome do Logradouro')
                    ->searchable()
                    ->weight('bold'),
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
                    ->url(function (LogradouroCemiterio $record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        
                        if ($record->geo_json && isset($record->geo_json->coordinates)) {
                            // Como no banco é MULTILINESTRING, o GeoJSON tem 3 níveis de profundidade:
                            // coordinates[0] = Primeira Linha
                            // coordinates[0][0] = Primeiro Ponto dessa linha (que é um array [lon, lat])
                            $ponto = $record->geo_json->coordinates[0][0] ?? null;
                            
                            if (is_array($ponto) && count($ponto) >= 2) {
                                $lon = $ponto[0];
                                $lat = $ponto[1];
                                return url('/app/' . $tenant->slug . '/mapa-interativo?layer=logradouros_cemiterio&focus_lat=' . $lat . '&focus_lon=' . $lon);
                            }
                        }
                        return null;
                    })
                    ->visible(fn (LogradouroCemiterio $record) => $record->geo_json !== null),

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
            'index' => Pages\ListLogradouroCemiterios::route('/'),
            'create' => Pages\CreateLogradouroCemiterio::route('/create'),
            'edit' => Pages\EditLogradouroCemiterio::route('/{record}/edit'),
        ];
    }
}