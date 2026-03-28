<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RuralPontoInteresseResource\Pages;
use App\Models\RuralPontoInteresse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class RuralPontoInteresseResource extends Resource
{
    use HasTenantModule;
    
    protected static ?string $tenantModule = 'rural';

    protected static ?string $model = RuralPontoInteresse::class;
    protected static ?string $navigationIcon = 'heroicon-o-star'; // Estrela para destacar os pontos
    protected static ?string $navigationGroup = 'Cadastro Rural';
    protected static ?string $modelLabel = 'Ponto de Interesse';
    protected static ?string $pluralModelLabel = 'Pontos de Interesse';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')->schema([
                Forms\Components\TextInput::make('nome')
                    ->label('Nome do Local (Ex: Escola São José, Igreja Matriz)')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\Select::make('categoria')
                    ->label('Categoria')
                    ->options([
                        'Educação' => 'Educação (Escolas, Creches)',
                        'Religião' => 'Religião (Igrejas, Capelas, Templos)',
                        'Saúde' => 'Saúde (Postos, Hospitais)',
                        'Segurança' => 'Segurança (Posto Policial)',
                        'Lazer e Cultura' => 'Lazer e Cultura (Praças, CTGs, Salões)',
                        'Associação' => 'Associação Comunitária',
                        'Outros' => 'Outros',
                    ])
                    ->required(),

                Forms\Components\Select::make('rural_localidade_id')
                    ->label('Localidade / Distrito Pertencente')
                    ->relationship('localidade', 'nome')
                    ->searchable()
                    ->preload(),
            ])->columns(3),

            Forms\Components\Section::make('Detalhes e Mapa')->schema([
                Forms\Components\Textarea::make('observacoes')
                    ->label('Observações Adicionais')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenadas GeoJSON (Ponto)')
                    ->rows(4)
                    ->columnSpanFull()
                    ->helperText('Deixe em branco se for marcar no mapa interativo.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('Cód')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('categoria')->label('Categoria')->badge()->searchable(),
                Tables\Columns\TextColumn::make('localidade.nome')->label('Localidade')->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categoria')
                    ->options([
                        'Escola' => 'Escola / Educação',
                        'Saúde' => 'Posto de Saúde',
                        'Igreja' => 'Igreja / Templo',
                        'Turismo' => 'Ponto Turístico / Lazer',
                        'Comércio' => 'Comércio Local',
                        'Outro' => 'Outro'
                    ]),
            ])
           
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function ($record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if ($record->geo_json && $record->geo_json->type === 'Point') {
                            $coords = $record->geo_json->coordinates;
                            if (isset($coords[0]) && isset($coords[1])) {
                                return url('/app/' . $tenant->slug . '/mapa-interativo?layer=rural-pontos-interesse&focus_lat=' . $coords[1] . '&focus_lon=' . $coords[0]);
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
            'index' => Pages\ListRuralPontoInteresses::route('/'),
            'create' => Pages\CreateRuralPontoInteresse::route('/create'),
            'edit' => Pages\EditRuralPontoInteresse::route('/{record}/edit'),
        ];
    }
}