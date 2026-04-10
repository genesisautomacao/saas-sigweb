<?php

namespace App\Filament\Resources;

use App\Models\PontoPanoramico;
use App\Filament\Resources\PontoPanoramicoResource\Pages;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class PontoPanoramicoResource extends Resource
{
    use HasTenantModule;
    
    protected static ?string $tenantModule = 'administrativo'; // Ou mude para o módulo que preferir
    protected static ?string $model = PontoPanoramico::class;
    protected static ?string $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationGroup = 'Módulo Administrativo';
    protected static ?string $modelLabel = 'Ponto Panorâmico 360º';
    protected static ?string $pluralModelLabel = 'Imagens 360º';


    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação e Arquivo')->schema([
                Forms\Components\TextInput::make('titulo')
                    ->label('Título do Local (Ex: Praça Matriz)')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\DatePicker::make('data_captura')
                    ->label('Data da Captura')
                    ->default(now()),

                Forms\Components\FileUpload::make('image_path')
                    ->label('Imagem 360º (Equirretangular)')
                    ->image()
                    ->directory('panoramas') // Salvará em storage/app/public/panoramas
                    ->helperText('Faça o upload de uma imagem JPG/PNG em formato 360º. Caso deixe em branco, o sistema usará uma imagem de simulação para testes.')
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Geolocalização')->schema([
                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenada Exata (Ponto)')
                    ->helperText('Exemplo: "-50.418257 -26.965895". Deixe em branco se preferir cadastrar clicando direto no mapa.')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('titulo')->label('Local')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('data_captura')->label('Data da Foto')->date('d/m/Y')->sortable(),
                
                // Indicador visual se é foto real ou simulação
                Tables\Columns\IconColumn::make('image_path')
                    ->label('Possui Arquivo')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->getStateUsing(fn ($record) => $record->image_path !== null),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function ($record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if ($record->geo_json && isset($record->geo_json->coordinates)) {
                            $coords = $record->geo_json->coordinates;
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=pontos_panoramicos&focus_lat=' . $coords[1] . '&focus_lon=' . $coords[0] . '&zoom=18');
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
            'index' => Pages\ListPontoPanoramicos::route('/'),
            'create' => Pages\CreatePontoPanoramico::route('/create'),
            'edit' => Pages\EditPontoPanoramico::route('/{record}/edit'),
        ];
    }
}