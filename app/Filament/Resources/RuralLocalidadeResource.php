<?php
namespace App\Filament\Resources;

use App\Filament\Resources\RuralLocalidadeResource\Pages;
use App\Models\RuralLocalidade;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class RuralLocalidadeResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'rural';

    protected static ?string $model = RuralLocalidade::class;
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Cadastro Rural';
    protected static ?string $modelLabel = 'Localidade / Distrito';
    protected static ?string $pluralModelLabel = 'Localidades Rurais';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação da Localidade')->schema([
                Forms\Components\TextInput::make('nome')->label('Nome da Localidade')->required()->maxLength(255),
                Forms\Components\Select::make('tipo')->label('Tipo')->options([
                    'Distrito' => 'Distrito',
                    'Localidade' => 'Localidade',
                    'Povoado' => 'Povoado',
                ])->required()->default('Localidade'),
                Forms\Components\Textarea::make('geo_json_input')->label('Coordenadas GeoJSON (Topografia)')->rows(4)->columnSpanFull()
                    ->helperText('Deixe em branco se for desenhar no mapa. Cole o GeoJSON gerado por sistemas de topografia caso já possua.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('Cód')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge(),
                Tables\Columns\TextColumn::make('area_geo')->label('Área (m²)')->numeric(decimalPlaces: 2)->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')->options(['Distrito' => 'Distrito', 'Localidade' => 'Localidade']),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function ($record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        // Extrai a primeira coordenada do MultiPolygon para focar o mapa
                        if ($record->geo_json && isset($record->geo_json->coordinates[0][0][0])) {
                            $lon = $record->geo_json->coordinates[0][0][0][0];
                            $lat = $record->geo_json->coordinates[0][0][0][1];
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=rural-localidades&focus_lat=' . $lat . '&focus_lon=' . $lon . '&zoom=15');
                        }
                        return null;
                    })
                    ->visible(fn($record) => $record->geo_json !== null),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([ Tables\Actions\BulkActionGroup::make([ Tables\Actions\DeleteBulkAction::make(), ]), ]);
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListRuralLocalidades::route('/'),
            'create' => Pages\CreateRuralLocalidade::route('/create'),
            'edit' => Pages\EditRuralLocalidade::route('/{record}/edit'),
        ];
    }
}