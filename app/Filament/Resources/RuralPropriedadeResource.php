<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RuralPropriedadeResource\Pages;
use App\Models\RuralPropriedade;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class RuralPropriedadeResource extends Resource
{
    use HasTenantModule;
    
    protected static ?string $tenantModule = 'rural';

    protected static ?string $model = RuralPropriedade::class;
    protected static ?string $navigationIcon = 'heroicon-o-home-modern';
    protected static ?string $navigationGroup = 'Cadastro Rural';
    protected static ?string $modelLabel = 'Propriedade Rural';
    protected static ?string $pluralModelLabel = 'Propriedades Rurais';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação e Vínculos')->schema([
                Forms\Components\TextInput::make('nome_propriedade')
                    ->label('Nome da Propriedade (Sítio/Fazenda)')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\Select::make('rural_localidade_id')
                    ->label('Localidade / Distrito')
                    ->relationship('localidade', 'nome')
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('pessoa_id')
                    ->label('Proprietário Responsável')
                    ->relationship('proprietario', 'name')
                    ->searchable()
                    ->preload(),
            ])->columns(3),

            Forms\Components\Section::make('Registros Fundiários')->schema([
                Forms\Components\TextInput::make('codigo_incra')
                    ->label('Código INCRA')
                    ->maxLength(255),
                
                Forms\Components\TextInput::make('codigo_car')
                    ->label('Código CAR (Cadastro Ambiental Rural)')
                    ->maxLength(255),

                Forms\Components\TextInput::make('codigo_sigef')
                    ->label('Código SIGEF')
                    ->maxLength(255),
            ])->columns(3),

            Forms\Components\Section::make('Dados Espaciais')->schema([
                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenadas GeoJSON (Polígono)')
                    ->rows(4)
                    ->columnSpanFull()
                    ->helperText('Deixe em branco se for desenhar no mapa. Cole o GeoJSON gerado por sistemas de topografia caso já possua.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('Cód')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('nome_propriedade')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('localidade.nome')->label('Localidade')->searchable(),
                Tables\Columns\TextColumn::make('proprietario.name')->label('Proprietário')->searchable(),
                Tables\Columns\TextColumn::make('codigo_car')->label('CAR')->searchable(),
                Tables\Columns\TextColumn::make('area_geo')->label('Área (m²)')->numeric(decimalPlaces: 2)->sortable(),
            ])
            ->filters([
                // Filtros adicionais se precisar
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function ($record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if ($record->geo_json && isset($record->geo_json->coordinates[0][0][0])) {
                            $lon = $record->geo_json->coordinates[0][0][0][0];
                            $lat = $record->geo_json->coordinates[0][0][0][1];
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=rural-propriedades&focus_lat=' . $lat . '&focus_lon=' . $lon . '&zoom=15');
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
            'index' => Pages\ListRuralPropriedades::route('/'),
            'create' => Pages\CreateRuralPropriedade::route('/create'),
            'edit' => Pages\EditRuralPropriedade::route('/{record}/edit'),
        ];
    }
}