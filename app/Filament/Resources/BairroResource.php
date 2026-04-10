<?php

namespace App\Filament\Resources;

use App\Models\Bairro;
use App\Filament\Resources\BairroResource\Pages;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class BairroResource extends Resource
{
    use HasTenantModule;
    
    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = Bairro::class;
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Módulo Imobiliário';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Bairro';
    protected static ?string $pluralModelLabel = 'Bairros';
    
    // Removi o "canCreate() { return false; }" para habilitar a criação

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação do Bairro')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome do Bairro')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\TextInput::make('setor')
                    ->label('Setor (Opcional)')
                    ->maxLength(255),
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
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('setor')->label('Setor')->searchable(),
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
                                return url('/app/' . $tenant->slug . '/mapa-interativo?layer=bairros&focus_lat=' . $coords[1] . '&focus_lon=' . $coords[0] . '&zoom=15');
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
            'index' => Pages\ListBairros::route('/'),
            'create' => Pages\CreateBairro::route('/create'),
            'edit' => Pages\EditBairro::route('/{record}/edit'),
        ];
    }
}