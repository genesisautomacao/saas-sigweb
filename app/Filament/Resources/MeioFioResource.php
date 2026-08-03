<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MeioFioResource\Pages;
use App\Models\MeioFio;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MeioFioResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = MeioFio::class;
    protected static ?string $navigationIcon = 'heroicon-o-minus';
    protected static ?string $navigationGroup = 'Módulo Imobiliário';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Meio-fio / Calçada';
    protected static ?string $modelLabel = 'Meio-fio';
    protected static ?string $pluralModelLabel = 'Meios-fio / Calçadas';
    protected static ?string $slug = 'meios-fio';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')->schema([
                Forms\Components\Select::make('logradouro_id')
                    ->label('Logradouro vinculado')
                    ->relationship('logradouro', 'name')
                    ->searchable()
                    ->preload(),
            ])->columns(3),

            // Refatoração PoC Tangará: material/estado viraram campos customizados (kit)
            Forms\Components\Section::make('Dados do Meio-fio')
                ->visible(fn () => \App\Services\Coleta\CampoCustomizadoService::definicoes('meio_fio')->isNotEmpty())
                ->schema(fn () => \App\Services\Coleta\CampoCustomizadoService::componentes('meio_fio'))
                ->columns(3),

            Forms\Components\Section::make('Observações')->schema([
                Forms\Components\Textarea::make('observacoes')
                    ->label('Observações')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Dados Espaciais')->schema([
                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenadas (GeoJSON LineString)')
                    ->helperText('Cole um GeoJSON de LineString ou uma lista de coordenadas separadas por vírgula (lon lat).')
                    ->rows(10)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('logradouro.name')->label('Logradouro')->searchable(),
                // Refatoração PoC Tangará: atributos em dados_customizados (JSONB)
                Tables\Columns\TextColumn::make('dados_customizados.material')->label('Material')->badge()->default('—'),
                Tables\Columns\TextColumn::make('dados_customizados.estado_conservacao')
                    ->label('Conservação')
                    ->badge()
                    ->default('—')
                    ->color(fn(?string $state) => match (mb_strtolower((string) $state)) {
                        'bom'     => 'success',
                        'regular' => 'warning',
                        'ruim'    => 'danger',
                        default   => 'gray',
                    }),
                Tables\Columns\TextColumn::make('extensao_geo')
                    ->label('Extensão (m)')
                    ->numeric(2, ',', '.')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function ($record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        $row = \Illuminate\Support\Facades\DB::table('meio_fios')
                            ->selectRaw('ST_X(ST_PointOnSurface(geo)) AS lon, ST_Y(ST_PointOnSurface(geo)) AS lat')
                            ->where('id', $record->id)
                            ->first();
                        if ($row && $row->lat && $row->lon) {
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=meio_fios&focus_lat=' . $row->lat . '&focus_lon=' . $row->lon . '&zoom=18');
                        }
                        return null;
                    })
                    ->visible(fn($record) => $record->geo_json !== null),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index'  => Pages\ListMeiosFio::route('/'),
            'create' => Pages\CreateMeioFio::route('/create'),
            'edit'   => Pages\EditMeioFio::route('/{record}/edit'),
        ];
    }
}
