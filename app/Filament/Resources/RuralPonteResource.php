<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RuralPonteResource\Pages;
use App\Models\RuralPonte;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class RuralPonteResource extends Resource
{
    use HasTenantModule;
    
    protected static ?string $tenantModule = 'rural';

    protected static ?string $model = RuralPonte::class;
    protected static ?string $navigationIcon = 'heroicon-o-bars-2'; // Um ícone que lembra os pilares/tabuleiro de uma ponte
    protected static ?string $navigationGroup = 'Cadastro Rural';
    protected static ?string $modelLabel = 'Ponte Rural';
    protected static ?string $pluralModelLabel = 'Pontes Rurais';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Localização e Identificação')->schema([
                Forms\Components\TextInput::make('nome_referencia')
                    ->label('Nome ou Referência (Ex: Ponte do Rio Caí)')
                    ->maxLength(255),
                
                Forms\Components\Select::make('rural_localidade_id')
                    ->label('Localidade / Distrito')
                    ->relationship('localidade', 'nome')
                    ->searchable()
                    ->preload(),
                
                Forms\Components\Select::make('rural_estrada_id')
                    ->label('Estrada Pertencente')
                    ->relationship('estrada', 'nome')
                    ->searchable()
                    ->preload(),
            ])->columns(3),

            Forms\Components\Section::make('Características Estruturais')->schema([
                Forms\Components\Select::make('material_construcao')
                    ->label('Material Predominante')
                    ->options([
                        'Madeira' => 'Madeira',
                        'Concreto' => 'Concreto',
                        'Metálica' => 'Metálica',
                        'Mista' => 'Mista (Madeira/Metálica, etc.)',
                    ]),

                Forms\Components\TextInput::make('capacidade_carga_toneladas')
                    ->label('Capacidade de Carga (Toneladas)')
                    ->numeric()
                    ->suffix('Ton'),

                Forms\Components\Select::make('estado_conservacao')
                    ->label('Estado de Conservação')
                    ->options([
                        'Ótimo' => 'Ótimo',
                        'Bom' => 'Bom',
                        'Regular' => 'Regular (Requer atenção)',
                        'Ruim' => 'Ruim (Requer manutenção)',
                        'Péssimo' => 'Péssimo (Risco de interdição)',
                    ]),
            ])->columns(3),

            Forms\Components\Section::make('Dados Espaciais')->schema([
                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenadas GeoJSON (Ponto)')
                    ->rows(4)
                    ->columnSpanFull()
                    ->helperText('Deixe em branco se for marcar no mapa. Cole o GeoJSON caso já possua as coordenadas (Point).'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('Cód')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('nome_referencia')->label('Referência')->searchable(),
                Tables\Columns\TextColumn::make('localidade.nome')->label('Localidade')->searchable(),
                Tables\Columns\TextColumn::make('estrada.nome')->label('Estrada')->searchable(),
                Tables\Columns\TextColumn::make('material_construcao')->label('Material')->searchable(),
                Tables\Columns\TextColumn::make('capacidade_carga_toneladas')->label('Carga (Ton)')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('estado_conservacao')->label('Condição')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('material_construcao')
                    ->options(['Madeira' => 'Madeira', 'Concreto' => 'Concreto', 'Metálica' => 'Metálica', 'Mista' => 'Mista']),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function ($record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        // Pontes são pontos geométricos simples
                        if ($record->geo_json && $record->geo_json->type === 'Point') {
                            $coords = $record->geo_json->coordinates;
                            if (isset($coords[0]) && isset($coords[1])) {
                                return url('/app/' . $tenant->slug . '/mapa-interativo?layer=rural-pontes&focus_lat=' . $coords[1] . '&focus_lon=' . $coords[0]);
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
            'index' => Pages\ListRuralPontes::route('/'),
            'create' => Pages\CreateRuralPonte::route('/create'),
            'edit' => Pages\EditRuralPonte::route('/{record}/edit'),
        ];
    }
}