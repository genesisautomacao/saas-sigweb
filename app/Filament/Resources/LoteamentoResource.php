<?php

namespace App\Filament\Resources;

use App\Models\Loteamento;
use App\Filament\Resources\LoteamentoResource\Pages;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class LoteamentoResource extends Resource
{
    use HasTenantModule;
    
    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = Loteamento::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Módulo Imobiliário';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Loteamento';
    protected static ?string $pluralModelLabel = 'Loteamentos';

    // 🛑 Removido o "canCreate() { return false; }" para habilitar a criação

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação do Loteamento')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome do Loteamento')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\TextInput::make('setor')
                    ->label('Setor (Opcional)')
                    ->maxLength(255),

                Forms\Components\TextInput::make('area_geo')
                    ->label('Área (m²)')
                    ->helperText('Calculada automaticamente da geometria.')
                    ->disabled()
                    ->dehydrated(false)
                    ->numeric()
                    ->suffix('m²'),
            ])->columns(2),

            Forms\Components\Section::make('Dados Espaciais')->schema([
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
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')->label('Nome do Loteamento')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('setor')->label('Setor')->searchable(),
                Tables\Columns\TextColumn::make('area_geo')
                    ->label('Área (m²)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->suffix(' m²')
                    ->sortable()
                    ->toggleable(),
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
                                return url('/app/' . $tenant->slug . '/mapa-interativo?layer=loteamentos&focus_lat=' . $coords[1] . '&focus_lon=' . $coords[0] . '&zoom=16');
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
            'index' => Pages\ListLoteamentos::route('/'),
            'create' => Pages\CreateLoteamento::route('/create'),
            'edit' => Pages\EditLoteamento::route('/{record}/edit'),
        ];
    }
}