<?php

namespace App\Filament\Resources;

use App\Models\Quadra;
use App\Filament\Resources\QuadraResource\Pages;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class QuadraResource extends Resource
{
    use HasTenantModule;
    
    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = Quadra::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Módulo Imobiliário';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'Quadra';
    protected static ?string $pluralModelLabel = 'Quadras';
    
    // 🛑 Removido o "canCreate() { return false; }" para habilitar a criação

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação da Quadra')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome / Número da Quadra')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\Select::make('bairro_id')
                    ->label('Bairro Pertencente')
                    ->relationship('bairro', 'name')
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('loteamento_id')
                    ->label('Loteamento Pertencente')
                    ->relationship('loteamento', 'name')
                    ->searchable()
                    ->preload(),
            ])->columns(3),

            Forms\Components\Section::make('Dados Espaciais')->schema([
                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenadas do Perímetro (Ou GeoJSON)')
                    ->helperText('Você pode colar um GeoJSON completo OU apenas uma lista de coordenadas no formato: "-50.404263 -26.972014, -50.401214 -26.974058..."')
                    ->rows(15)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Quadra')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('bairro.name')->label('Bairro')->searchable(),
                Tables\Columns\TextColumn::make('loteamento.name')->label('Loteamento')->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bairro_id')
                    ->label('Filtrar por Bairro')
                    ->relationship('bairro', 'name')
                    ->multiple()
                    ->preload(),
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
                                return url('/app/' . $tenant->slug . '/mapa-interativo?layer=quadras&focus_lat=' . $coords[1] . '&focus_lon=' . $coords[0] . '&zoom=17');
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
            'index' => Pages\ListQuadras::route('/'),
            'create' => Pages\CreateQuadra::route('/create'),
            'edit' => Pages\EditQuadra::route('/{record}/edit'),
        ];
    }
}