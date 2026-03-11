<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JazigoResource\Pages;
use App\Models\Jazigo;
use App\Models\QuadraCemiterio;
use App\Models\Pessoa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class JazigoResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'cemiterio';

    protected static ?string $model = Jazigo::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Gestão de Cemitérios';
    protected static ?string $modelLabel = 'Jazigo / Túmulo';
    protected static ?string $pluralModelLabel = 'Jazigos';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Localização')
                    ->schema([
                        Forms\Components\Select::make('quadra_cemiterio_id')
                            ->label('Quadra (Cemitério)')
                            ->options(function () {
                                // Mostra a quadra e o nome do cemitério no Select
                                return QuadraCemiterio::with('cemiterio')->get()->mapWithKeys(function ($q) {
                                    $cemiterio = $q->cemiterio ? $q->cemiterio->name : 'Sem Cemitério';
                                    return [$q->id => $q->name . ' (' . $cemiterio . ')'];
                                });
                            })
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('codigo')
                            ->label('Identificação/Código (Ex: J-15, A-02)')
                            ->required()
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Dados Operacionais')
                    ->schema([
                        Forms\Components\Select::make('tipo')
                            ->label('Tipo de Estrutura')
                            ->options([
                                'gaveta' => 'Gaveta / Carneira',
                                'chao' => 'Sepultura de Chão / Terra',
                                'mausoleu' => 'Mausoléu / Capela',
                            ])
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status Atual')
                            ->options([
                                'disponivel' => '🟢 Disponível',
                                'ocupado' => '🔴 Ocupado',
                                'manutencao' => '🟡 Em Manutenção',
                            ])
                            ->default('disponivel')
                            ->required(),

                        Forms\Components\Select::make('proprietario_id')
                            ->label('Proprietário / Concessionário (Opcional)')
                            ->options(fn() => Pessoa::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Geometria (Polígono)')
                    ->description('Se não desenhou no mapa, cole o código GeoJSON gerado pelo QGIS.')
                    ->schema([
                        Forms\Components\Textarea::make('geo_json_input')
                            ->label('Código GeoJSON')
                            ->placeholder('{"type": "Polygon", "coordinates": [[[...]]]}')
                            ->rows(6)
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),

                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('quadraCemiterio.name')
                    ->label('Quadra')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'disponivel' => 'success',
                        'ocupado' => 'danger',
                        'manutencao' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('proprietario.name')
                    ->label('Proprietário')
                    ->searchable()
                    ->default('Nenhum'),

                Tables\Columns\TextColumn::make('area_geo')
                    ->label('Área')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->suffix(' m²')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'disponivel' => 'Disponível',
                        'ocupado' => 'Ocupado',
                        'manutencao' => 'Em Manutenção',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function (Jazigo $record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if ($record->geo_json && isset($record->geo_json->coordinates[0][0][0])) {
                            $lon = $record->geo_json->coordinates[0][0][0][0]; 
                            $lat = $record->geo_json->coordinates[0][0][0][1];
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=jazigos&focus_lat=' . $lat . '&focus_lon=' . $lon);
                        }
                        return null;
                    })
                    ->visible(fn (Jazigo $record) => $record->geo_json !== null),

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
            'index' => Pages\ListJazigos::route('/'),
            'create' => Pages\CreateJazigo::route('/create'),
            'edit' => Pages\EditJazigo::route('/{record}/edit'),
        ];
    }
}