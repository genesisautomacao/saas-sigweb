<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PatrimonioPublicoResource\Pages;
use App\Filament\Resources\PatrimonioPublicoResource\RelationManagers\DocumentosRelationManager;
use App\Models\PatrimonioPublico;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PatrimonioPublicoResource extends Resource
{
    use HasTenantModule;
    
    protected static ?string $tenantModule = 'patrimonios';

    protected static ?string $model = PatrimonioPublico::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Patrimônios Públicos';
    protected static ?string $modelLabel = 'Patrimônio Público';
    protected static ?string $pluralModelLabel = 'Patrimônios Públicos';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome do Patrimônio (Ex: Praça Matriz)')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\Select::make('tipo_patrimonio_id')
                    ->label('Tipo de Patrimônio')
                    ->relationship('tipo', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('address')
                    ->label('Endereço / Localização Básica')
                    ->maxLength(255),
            ])->columns(3),

            Forms\Components\Section::make('Detalhes e Geometria')->schema([
                Forms\Components\Textarea::make('description')
                    ->label('Descrição')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenadas GeoJSON (Qualquer Geometria)')
                    ->rows(4)
                    ->columnSpanFull()
                    ->helperText('Deixe em branco se for desenhar no mapa interativo. Suporta Pontos, Linhas ou Polígonos.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('Cód')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('tipo.name')->label('Tipo')->badge()->searchable(),
                Tables\Columns\TextColumn::make('address')->label('Endereço')->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function ($record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if ($record->geo_json) {
                            // Extrator genérico de coordenadas para centralizar o mapa (suporta ponto, linha ou polígono)
                            $coords = null;
                            if ($record->geo_json->type === 'Point') {
                                $coords = $record->geo_json->coordinates;
                            } elseif ($record->geo_json->type === 'LineString') {
                                $coords = $record->geo_json->coordinates[0];
                            } elseif (in_array($record->geo_json->type, ['Polygon', 'MultiLineString'])) {
                                $coords = $record->geo_json->coordinates[0][0];
                            } elseif ($record->geo_json->type === 'MultiPolygon') {
                                $coords = $record->geo_json->coordinates[0][0][0];
                            }

                            if (isset($coords[0]) && isset($coords[1])) {
                                return url('/app/' . $tenant->slug . '/mapa-interativo?layer=patrimonio_publicos&focus_lat=' . $coords[1] . '&focus_lon=' . $coords[0] . '&zoom=18');
                            }
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

    public static function getRelations(): array
    {
        return [
            DocumentosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatrimonioPublicos::route('/'),
            'create' => Pages\CreatePatrimonioPublico::route('/create'),
            'edit' => Pages\EditPatrimonioPublico::route('/{record}/edit'),
        ];
    }
}