<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SetorFiscalResource\Pages;
use App\Models\SetorFiscal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class SetorFiscalResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'pgv';

    protected static ?string $model = SetorFiscal::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $modelLabel = 'Setor Fiscal';
    protected static ?string $pluralModelLabel = 'Setores Fiscais';
    protected static ?string $navigationGroup = 'Gestão Tributária (PGV)';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação do Setor')
                    ->schema([
                        Forms\Components\TextInput::make('nome')
                            ->label('Nome do Setor')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('pgv_parametro_id')
                            ->label('Parâmetro Base de Valor (Regra)')
                            ->relationship('parametro', 'nome_padrao')
                            ->required()
                            ->preload()
                            ->searchable(),

                        Forms\Components\Textarea::make('descricao')
                            ->label('Descrição / Observações')
                            ->columnSpanFull(),
                    ])->columns(2),

                // O PADRÃO OURO DE GEOJSON ESPELHADO DO LOTE
                Forms\Components\Section::make('Geometria do Setor (Polígono)')
                    ->description('Caso não desenhado no mapa, insira o GeoJSON gerado pela topografia.')
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
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nome')
                    ->label('Setor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parametro.nome_padrao')
                    ->label('Regra de Valor Aplicada')
                    ->badge()
                    ->color('success')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
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
            'index' => Pages\ListSetorFiscals::route('/'),
            'create' => Pages\CreateSetorFiscal::route('/create'),
            'edit' => Pages\EditSetorFiscal::route('/{record}/edit'),
        ];
    }
}
