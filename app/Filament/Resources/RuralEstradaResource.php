<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RuralEstradaResource\Pages;
use App\Models\RuralEstrada;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class RuralEstradaResource extends Resource
{
    use HasTenantModule;
    
    protected static ?string $tenantModule = 'rural';

    protected static ?string $model = RuralEstrada::class;
    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';
    protected static ?string $navigationGroup = 'Cadastro Rural';
    protected static ?string $modelLabel = 'Estrada / Vicinal';
    protected static ?string $pluralModelLabel = 'Estradas Rurais';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')->schema([
                Forms\Components\TextInput::make('nome')
                    ->label('Nome da Estrada (Ex: Linha 2, Estrada da Uva)')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\Select::make('rural_localidade_id')
                    ->label('Localidade / Distrito')
                    ->relationship('localidade', 'nome')
                    ->searchable()
                    ->preload(),
            ])->columns(2),

            Forms\Components\Section::make('Características Viárias')->schema([
                Forms\Components\Select::make('tipo')
                    ->label('Tipo de Via')
                    ->options([
                        'Principal' => 'Principal',
                        'Secundária' => 'Secundária',
                        'Vicinal' => 'Vicinal',
                    ])
                    ->default('Vicinal')
                    ->required(),
                
                Forms\Components\Select::make('tipo_pavimento')
                    ->label('Pavimentação')
                    ->options([
                        'Terra' => 'Terra',
                        'Cascalho' => 'Cascalho',
                        'Asfalto' => 'Asfalto',
                        'Calçamento' => 'Calçamento (Paralelepípedo/Bloco)',
                    ]),

                Forms\Components\Select::make('condicao_trafego')
                    ->label('Condição de Tráfego')
                    ->options([
                        'Boa' => 'Boa',
                        'Ruim' => 'Ruim',
                        'Intransitável' => 'Intransitável',
                    ]),
            ])->columns(3),

            Forms\Components\Section::make('Dados Espaciais')->schema([
                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenadas GeoJSON (Linha / LineString)')
                    ->rows(4)
                    ->columnSpanFull()
                    ->helperText('Deixe em branco se for desenhar no mapa. Cole o GeoJSON caso já possua.'),
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
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge(),
                Tables\Columns\TextColumn::make('tipo_pavimento')->label('Pavimento')->searchable(),
                Tables\Columns\TextColumn::make('extensao_geo')->label('Extensão (m)')->numeric(decimalPlaces: 2)->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->options(['Principal' => 'Principal', 'Secundária' => 'Secundária', 'Vicinal' => 'Vicinal']),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function ($record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        // Como a estrada é LineString ou MultiLineString, pegamos o primeiro ponto para focar o mapa
                        if ($record->geo_json) {
                            $coords = $record->geo_json->type === 'MultiLineString' 
                                ? $record->geo_json->coordinates[0][0] 
                                : $record->geo_json->coordinates[0];

                            if (isset($coords[0]) && isset($coords[1])) {
                                return url('/app/' . $tenant->slug . '/mapa-interativo?layer=rural-estradas&focus_lat=' . $coords[1] . '&focus_lon=' . $coords[0]);
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
            'index' => Pages\ListRuralEstradas::route('/'),
            'create' => Pages\CreateRuralEstrada::route('/create'),
            'edit' => Pages\EditRuralEstrada::route('/{record}/edit'),
        ];
    }
}