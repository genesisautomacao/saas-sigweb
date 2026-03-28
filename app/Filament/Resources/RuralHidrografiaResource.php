<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RuralHidrografiaResource\Pages;
use App\Models\RuralHidrografia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class RuralHidrografiaResource extends Resource
{
    use HasTenantModule;
    
    protected static ?string $tenantModule = 'rural';

    protected static ?string $model = RuralHidrografia::class;
    protected static ?string $navigationIcon = 'heroicon-o-flag'; // Ícone alternativo visual
    protected static ?string $navigationGroup = 'Cadastro Rural';
    protected static ?string $modelLabel = 'Hidrografia';
    protected static ?string $pluralModelLabel = 'Hidrografias';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')->schema([
                Forms\Components\TextInput::make('nome')
                    ->label('Nome do Corpo D\'água (Ex: Rio Caí)')
                    ->maxLength(255),
                
                Forms\Components\Select::make('rural_localidade_id')
                    ->label('Localidade / Distrito de Referência')
                    ->relationship('localidade', 'nome')
                    ->searchable()
                    ->preload(),
                
                Forms\Components\Select::make('tipo')
                    ->label('Classificação')
                    ->options([
                        'Rio' => 'Rio (Linha/Polígono)',
                        'Arroio' => 'Arroio (Linha)',
                        'Lago' => 'Lago/Açude (Polígono)',
                        'Nascente' => 'Nascente (Ponto)',
                    ])
                    ->default('Rio')
                    ->required(),
            ])->columns(3),

            Forms\Components\Section::make('Dados Espaciais')->schema([
                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenadas GeoJSON (Qualquer Geometria)')
                    ->rows(4)
                    ->columnSpanFull()
                    ->helperText('Deixe em branco se for desenhar no mapa. Suporta Pontos, Linhas ou Polígonos.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('Cód')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('localidade.nome')->label('Localidade')->searchable(),
                Tables\Columns\TextColumn::make('tipo')->label('Classificação')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->options([
                        'Rio' => 'Rio',
                        'Arroio' => 'Arroio',
                        'Lago' => 'Lago',
                        'Nascente' => 'Nascente'
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function ($record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if ($record->geo_json) {
                            // Algoritmo para extrair o ponto central independente do tipo de geometria
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
                                return url('/app/' . $tenant->slug . '/mapa-interativo?layer=rural-hidrografias&focus_lat=' . $coords[1] . '&focus_lon=' . $coords[0]);
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRuralHidrografias::route('/'),
            'create' => Pages\CreateRuralHidrografia::route('/create'),
            'edit' => Pages\EditRuralHidrografia::route('/{record}/edit'),
        ];
    }
}