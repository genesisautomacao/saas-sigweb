<?php
namespace App\Filament\Resources;

use App\Models\Zona;
use App\Filament\Resources\ZonaResource\Pages;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class ZonaResource extends Resource
{
    use HasTenantModule;
    
    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = Zona::class;
    protected static ?string $navigationIcon = 'heroicon-o-globe-americas';
    protected static ?string $navigationGroup = 'Módulo Imobiliário';
    protected static ?int $navigationSort = 0;
    protected static ?string $modelLabel = 'Zona de Uso';
    protected static ?string $pluralModelLabel = 'Zoneamento';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação da Zona')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome da Zona (Ex: Zona Residencial 1)')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\TextInput::make('sigla')
                    ->label('Sigla (Ex: ZR-1)')
                    ->required()
                    ->maxLength(50),

                // Distrito a que pertence
                Forms\Components\Select::make('perimetro_id')
                    ->label('Distrito')
                    ->options(fn() => \App\Models\PerimetroUrbano::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                // CORRIGIDO: Forçando o output para RGB
                Forms\Components\ColorPicker::make('rgb')
                    ->label('Cor de Exibição no Mapa')
                    ->rgb() 
                    ->formatStateUsing(function ($state) {
                        if ($state && !str_contains($state, 'rgb') && !str_contains($state, '#')) {
                            // Limpa parênteses se houver e formata
                            $clean = str_replace(['(', ')'], '', $state);
                            return "rgb({$clean})";
                        }
                        return $state;
                    })
                    ->required(),
            ])->columns(4), // Mudei para 4 colunas para caber o perímetro bonito

            Forms\Components\Section::make('Limites Geográficos')->schema([
                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenadas do Perímetro (Ou GeoJSON)')
                    ->helperText('Você pode colar um GeoJSON completo OU apenas uma lista de coordenadas no formato: "-50.404263 -26.972014, -50.401214 -26.974058..."')
                    ->rows(15)
                    ->columnSpanFull(),
            ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('sigla')->label('Sigla')->badge()->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Nome da Zona')->sortable()->searchable()->weight('bold'),
                
                // CORRIGIDO: Leitura à prova de falhas para a cor na tabela
                Tables\Columns\TextColumn::make('rgb')
                    ->label('Cor no Mapa')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return '-';
                        
                        $corCSS = $state;
                        if (!str_contains($corCSS, 'rgb') && !str_contains($corCSS, '#')) {
                            $clean = str_replace(['(', ')'], '', $corCSS);
                            $corCSS = "rgb({$clean})";
                        }
                        
                        return new \Illuminate\Support\HtmlString("
                            <div class='flex items-center gap-2'>
                                <div style='background-color: {$corCSS};' class='w-6 h-6 rounded-full border border-gray-300 shadow-sm'></div>
                                <span class='text-xs text-gray-500 font-mono'>{$corCSS}</span>
                            </div>
                        ");
                    })
                    ->html(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function ($record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if ($record->geo_json) {
                            $coords = null;
                            if ($record->geo_json->type === 'Polygon') {
                                $coords = $record->geo_json->coordinates[0][0];
                            } elseif ($record->geo_json->type === 'MultiPolygon') {
                                $coords = $record->geo_json->coordinates[0][0][0];
                            }
                            if (isset($coords[0]) && isset($coords[1])) {
                                // Adicionado &sigla= para o mapa saber qual ligar
                                return url('/app/' . $tenant->slug . '/mapa-interativo?layer=zonas&sigla=' . $record->sigla . '&focus_lat=' . $coords[1] . '&focus_lon=' . $coords[0] . '&zoom=14');
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
            'index' => Pages\ListZonas::route('/'),
            'create' => Pages\CreateZona::route('/create'),
            'edit' => Pages\EditZona::route('/{record}/edit'),
        ];
    }
}