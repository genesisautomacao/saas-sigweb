<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PosteResource\Pages;
use App\Models\Poste;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class PosteResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'iluminacao';

    protected static ?string $model = Poste::class;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';
    protected static ?string $navigationGroup = 'Iluminação Pública';
    protected static ?string $modelLabel = 'Poste / Ponto de Luz';
    protected static ?string $pluralModelLabel = 'Postes e Pontos de Luz';
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
                            ->readOnly(fn() => request()->has('lat')) // 🛑 Trava o campo se o dado veio da URL
                            ->live(debounce: 800)
                            ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => static::updateNearestLogradouro($get, $set)),

                        Forms\Components\TextInput::make('longitude')
                            ->label('Longitude (X)')
                            ->numeric()
                            ->default(request()->query('lon'))
                            ->readOnly(fn() => request()->has('lon')) // 🛑 Trava o campo se o dado veio da URL
                            ->live(debounce: 800)
                            ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => static::updateNearestLogradouro($get, $set)),

                        // O AutoComplete Turbinado
                        Forms\Components\Select::make('logradouros')
                            ->label('Logradouro(s) Iluminado(s)')
                            ->relationship('logradouros', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull()
                            ->default(function () {
                                // 🛑 A MÁGICA DO LOAD: Se tiver Lat/Lon na URL, busca a rua na hora de abrir a tela!
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
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('tipo_poste_id')
                            ->label('Tipo de Poste')
                            ->relationship('tipoPoste', 'name')
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Novo Tipo de Poste')
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('structural_condition')
                            ->label('Condição Estrutural')
                            ->options([
                                'Bom' => 'Bom',
                                'Regular' => 'Regular',
                                'Ruim' => 'Ruim',
                            ]),

                        Forms\Components\TextInput::make('height')
                            ->label('Altura do Poste (m)')
                            ->numeric(),

                        Forms\Components\DatePicker::make('installation_date')
                            ->label('Data de Instalação')
                            ->displayFormat('d/m/Y'),
                    ])->columns(2),

                Forms\Components\Section::make('Dados da Luminária')
                    ->schema([
                        Forms\Components\Select::make('luminaire_type')
                            ->label('Tipo de Luminária')
                            ->options([
                                'LED' => 'LED',
                                'Vapor de Sódio' => 'Vapor de Sódio',
                                'Vapor Metálico' => 'Vapor Metálico',
                                'Mercúrio' => 'Mercúrio',
                                'Outros' => 'Outros',
                            ]),

                        Forms\Components\TextInput::make('lamp_power')
                            ->label('Potência (ex: 150W)'),

                        Forms\Components\TextInput::make('lamp_quantity')
                            ->label('Qtd. de Lâmpadas')
                            ->numeric(),

                        Forms\Components\TextInput::make('luminaire_height')
                            ->label('Altura da Luminária (m)')
                            ->numeric(),
                    ])->columns(2),

                Forms\Components\Section::make('Observações')
                    ->schema([
                        Forms\Components\Textarea::make('observations')
                            ->label('Anotações Técnicas')
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

                Tables\Columns\TextColumn::make('address')
                    ->label('Endereço')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('tipoPoste.name')
                    ->label('Tipo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('luminaire_type')
                    ->label('Luminária')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'LED' => 'success',
                        'Vapor de Sódio' => 'warning',
                        'Vapor Metálico' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('lamp_power')
                    ->label('Potência'),

                Tables\Columns\TextColumn::make('structural_condition')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Bom' => 'success',
                        'Regular' => 'warning',
                        'Ruim' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('luminaire_type')
                    ->label('Luminária')
                    ->options([
                        'LED' => 'LED',
                        'Vapor de Sódio' => 'Vapor de Sódio',
                    ]),
                Tables\Filters\SelectFilter::make('structural_condition')
                    ->label('Condição')
                    ->options([
                        'Bom' => 'Bom',
                        'Regular' => 'Regular',
                        'Ruim' => 'Ruim',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function (Poste $record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if ($record->geo_json && isset($record->geo_json->coordinates)) {
                            $lon = $record->geo_json->coordinates[0];
                            $lat = $record->geo_json->coordinates[1];
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=postes&focus_lat=' . $lat . '&focus_lon=' . $lon);
                        }
                        return null;
                    })
                    ->visible(fn(Poste $record) => $record->geo_json !== null),

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
            'index' => Pages\ListPostes::route('/'),
            'create' => Pages\CreatePoste::route('/create'),
            'edit' => Pages\EditPoste::route('/{record}/edit'),
        ];
    }

    /**
     * O RADAR POSTGIS: Busca a rua mais próxima baseada na Lat/Lon
     */
    public static function updateNearestLogradouro(Forms\Get $get, Forms\Set $set)
    {
        $lat = $get('latitude');
        $lon = $get('longitude');

        // Só dispara se tiver os dois preenchidos
        if (!$lat || !$lon) {
            return;
        }

        $tenant = \Filament\Facades\Filament::getTenant();

        try {
            // Busca o Logradouro mais próximo (raio livre, ordena pela menor distância)
            // ST_DistanceSphere retorna a distância em metros
            $nearest = \App\Models\Logradouro::query()
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('geo')
                ->orderByRaw("ST_DistanceSphere(geo, ST_SetSRID(ST_MakePoint(?, ?), 4326))", [$lon, $lat])
                ->first();

            if ($nearest) {
                // Como é um campo multiple (belongsToMany), enviamos um array com o ID
                $set('logradouros', [$nearest->id]);

                \Filament\Notifications\Notification::make()
                    ->title('Logradouro Detectado!')
                    ->body("O poste foi vinculado a: {$nearest->name}")
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            // Se a tabela de logradouros estiver vazia ou der erro, silenciamos para não travar a tela
            \Filament\Notifications\Notification::make()
                ->title('Não foi possível localizar o logradouro automaticamente.')
                ->warning()
                ->send();
        }
    }
}