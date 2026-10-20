<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SetorPgvResource\Pages;
use App\Models\SetorPgv;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class SetorPgvResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'pgv';

    protected static ?string $model = SetorPgv::class;

    // B9: até 2026-10-19 era o "SetorFiscal" (tabela setores_fiscais) — o nome ficou
    // com o setor da inscrição imobiliária (SetorFiscalResource, Base Cartográfica).
    protected static ?string $slug = 'setores-pgv';
    protected static ?string $tenantRelationshipName = 'setoresPgv';

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $modelLabel = 'Setor da PGV';
    protected static ?string $pluralModelLabel = 'Setores da PGV';
    protected static ?string $navigationGroup = 'Gestão Tributária (PGV)';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Item 75 — campos customizados do município nesta camada
                Forms\Components\Section::make('Campos do Município')
                    ->visible(fn () => \App\Services\Coleta\CampoCustomizadoService::definicoes('setor_pgv')->isNotEmpty())
                    ->schema(fn () => \App\Services\Coleta\CampoCustomizadoService::componentes('setor_pgv'))
                    ->columns(3),

                Forms\Components\Section::make('Identificação do Setor')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código do Setor')
                            ->helperText('Código do cadastro municipal.')
                            ->maxLength(50),

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

                Tables\Columns\TextColumn::make('codigo')->label('Código')->searchable()->sortable()->placeholder('—'),

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
            'index' => Pages\ListSetorPgvs::route('/'),
            'create' => Pages\CreateSetorPgv::route('/create'),
            'edit' => Pages\EditSetorPgv::route('/{record}/edit'),
        ];
    }
}
