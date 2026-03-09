<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArvoreResource\Pages;
use App\Models\Arvore;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class ArvoreResource extends Resource
{
    use HasTenantModule;

    // Você pode criar um módulo 'arborizacao' no futuro, ou deixar num genérico
    protected static ?string $tenantModule = 'arborizacao';

    protected static ?string $model = Arvore::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles'; // Ícone temporário, o Filament v3 não tem tree nativo no heroicons, mas pode usar outro de sua preferência
    protected static ?string $navigationGroup = 'Meio Ambiente';
    protected static ?string $modelLabel = 'Árvore / Indivíduo Arbóreo';
    protected static ?string $pluralModelLabel = 'Árvores e Arborização';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Geolocalização e Endereço')
                    ->description('Insira as coordenadas para o sistema buscar a rua mais próxima automaticamente.')
                    ->icon('heroicon-o-map')
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->label('Latitude (Y)')
                            ->numeric()
                            ->default(request()->query('lat'))
                            ->readOnly(fn() => request()->has('lat')) // Trava se veio do mapa
                            ->live(debounce: 800)
                            ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => static::updateNearestLogradouro($get, $set)),

                        Forms\Components\TextInput::make('longitude')
                            ->label('Longitude (X)')
                            ->numeric()
                            ->default(request()->query('lon'))
                            ->readOnly(fn() => request()->has('lon')) // Trava se veio do mapa
                            ->live(debounce: 800)
                            ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => static::updateNearestLogradouro($get, $set)),

                        // O AutoComplete Turbinado (Radar PostGIS)
                        Forms\Components\Select::make('logradouros')
                            ->label('Logradouro(s) Vinculado(s)')
                            ->relationship('logradouros', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull()
                            ->default(function () {
                                $lat = request()->query('lat');
                                $lon = request()->query('lon');

                                if ($lat && $lon) {
                                    $tenant = \Filament\Facades\Filament::getTenant();
                                    try {
                                        $nearest = \App\Models\Logradouro::query()
                                            ->where('tenant_id', $tenant->id)
                                            ->whereNotNull('geo')
                                            ->orderByRaw("ST_DistanceSphere(geo, ST_SetSRID(ST_MakePoint(?, ?), 4326))", [$lon, $lat])
                                            ->first();

                                        return $nearest ? [$nearest->id] : [];
                                    } catch (\Exception $e) {
                                        return [];
                                    }
                                }
                                return [];
                            }),

                        Forms\Components\TextInput::make('address')
                            ->label('Referência Extra (Opcional)')
                            ->placeholder('Ex: Em frente ao nº 150')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Dados Biométricos e Botânicos')
                    ->schema([
                        Forms\Components\TextInput::make('botanical_species')
                            ->label('Espécie Botânica')
                            ->placeholder('Ex: Ipê Amarelo'),
                            
                        Forms\Components\TextInput::make('botanical_family')
                            ->label('Família Botânica')
                            ->placeholder('Ex: Bignoniaceae'),

                        Forms\Components\Select::make('size')
                            ->label('Porte da Árvore')
                            ->options([
                                'pequeno' => 'Pequeno',
                                'medio' => 'Médio',
                                'grande' => 'Grande',
                            ]),
                            
                        Forms\Components\TextInput::make('trunk_diameter_dap')
                            ->label('Diâmetro do Tronco (DAP - cm)')
                            ->numeric(),
                            
                        Forms\Components\TextInput::make('canopy_diameter')
                            ->label('Diâmetro da Copa (m)')
                            ->numeric(),
                            
                        Forms\Components\TextInput::make('total_height')
                            ->label('Altura Total (m)')
                            ->numeric(),
                            
                        Forms\Components\TextInput::make('canopy_height')
                            ->label('Altura da Primeira Forquilha (m)')
                            ->numeric(),
                    ])->columns(2),

                Forms\Components\Section::make('Condições e Riscos')
                    ->schema([
                        Forms\Components\Select::make('phytosanitary_condition')
                            ->label('Condição Fitossanitária')
                            ->options([
                                'Boa' => 'Boa',
                                'Regular' => 'Regular',
                                'Ruim' => 'Ruim',
                                'Morta' => 'Morta',
                            ]),
                            
                        Forms\Components\TextInput::make('general_state')
                            ->label('Estado Geral')
                            ->placeholder('Ex: Sadia, Necessita Poda...'),
                            
                        Forms\Components\TextInput::make('root_system')
                            ->label('Sistema Radicular')
                            ->placeholder('Ex: Danificando passeio público...'),
                            
                        Forms\Components\TextInput::make('urban_interferences')
                            ->label('Interferências Urbanas')
                            ->placeholder('Ex: Conflito com rede elétrica...'),
                            
                        Forms\Components\Select::make('risk_potential')
                            ->label('Potencial de Risco (1 a 5)')
                            ->options([
                                1 => '1 - Muito Baixo',
                                2 => '2 - Baixo',
                                3 => '3 - Médio',
                                4 => '4 - Alto',
                                5 => '5 - Crítico',
                            ]),
                    ])->columns(2),

                Forms\Components\Section::make('Observações')
                    ->schema([
                        Forms\Components\Textarea::make('observations')
                            ->label('Anotações Técnicas / Laudo')
                            ->rows(3)
                            ->columnSpanFull(),
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

                Tables\Columns\TextColumn::make('botanical_species')
                    ->label('Espécie')
                    ->searchable()
                    ->default('Não identificada'),

                Tables\Columns\TextColumn::make('address')
                    ->label('Referência / Endereço')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('size')
                    ->label('Porte')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pequeno' => 'info',
                        'medio' => 'warning',
                        'grande' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('phytosanitary_condition')
                    ->label('Saúde')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Boa' => 'success',
                        'Regular' => 'warning',
                        'Ruim' => 'danger',
                        'Morta' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('risk_potential')
                    ->label('Risco')
                    ->color(fn (?int $state): string => match ($state) {
                        1, 2 => 'success',
                        3 => 'warning',
                        4, 5 => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('size')
                    ->label('Porte')
                    ->options([
                        'pequeno' => 'Pequeno',
                        'medio' => 'Médio',
                        'grande' => 'Grande',
                    ]),
                Tables\Filters\SelectFilter::make('phytosanitary_condition')
                    ->label('Saúde')
                    ->options([
                        'Boa' => 'Boa',
                        'Regular' => 'Regular',
                        'Ruim' => 'Ruim',
                        'Morta' => 'Morta',
                    ]),
            ])
            ->actions([
                // 🛑 Botão Mágico: Voar pro Mapa!
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function (Arvore $record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if ($record->geo_json && isset($record->geo_json->coordinates)) {
                            $lon = $record->geo_json->coordinates[0];
                            $lat = $record->geo_json->coordinates[1];
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=arvores&focus_lat=' . $lat . '&focus_lon=' . $lon);
                        }
                        return null;
                    })
                    ->visible(fn (Arvore $record) => $record->geo_json !== null),

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
            'index' => Pages\ListArvores::route('/'),
            'create' => Pages\CreateArvore::route('/create'),
            'edit' => Pages\EditArvore::route('/{record}/edit'),
        ];
    }

    /**
     * O RADAR POSTGIS: Busca a rua mais próxima baseada na Lat/Lon
     */
    public static function updateNearestLogradouro(Forms\Get $get, Forms\Set $set)
    {
        $lat = $get('latitude');
        $lon = $get('longitude');

        if (!$lat || !$lon) return;

        $tenant = \Filament\Facades\Filament::getTenant();

        try {
            $nearest = \App\Models\Logradouro::query()
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('geo')
                ->orderByRaw("ST_DistanceSphere(geo, ST_SetSRID(ST_MakePoint(?, ?), 4326))", [$lon, $lat])
                ->first();

            if ($nearest) {
                $set('logradouros', [$nearest->id]);
                \Filament\Notifications\Notification::make()
                    ->title('Logradouro Detectado!')
                    ->body("A árvore foi vinculada a: {$nearest->name}")
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            // Silencia para não quebrar a tela
        }
    }
}