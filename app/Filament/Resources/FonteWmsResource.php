<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FonteWmsResource\Pages;
use App\Models\FonteWms;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FonteWmsResource extends Resource
{
    protected static ?string $model = FonteWms::class;

    protected static ?string $tenantRelationshipName = 'fontesWms';

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'WMS';

    protected static ?string $modelLabel = 'Fonte WMS';

    protected static ?string $pluralModelLabel = 'Fontes WMS';

    protected static ?int $navigationSort = 91;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')->schema([
                Forms\Components\TextInput::make('nome')
                    ->label('Nome da Fonte (ex.: Ortofoto IBGE 2023)')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('categoria_wms_id')
                    ->label('Categoria')
                    ->relationship('categoria', 'nome')
                    ->searchable()
                    ->preload()
                    ->helperText('Agrupa a fonte no painel de camadas do mapa.'),
            ])->columns(2),

            Forms\Components\Section::make('Serviço OGC (WMS)')->schema([
                Forms\Components\TextInput::make('url')
                    ->label('URL do serviço WMS')
                    ->required()
                    ->url()
                    ->columnSpanFull()
                    ->placeholder('https://geoserver.exemplo.gov.br/wms'),

                Forms\Components\TextInput::make('camadas')
                    ->label('Nome da(s) camada(s) — parâmetro LAYERS')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('formato')
                    ->label('Formato')
                    ->options([
                        'image/png' => 'PNG (com transparência)',
                        'image/jpeg' => 'JPEG',
                    ])
                    ->default('image/png'),

                Forms\Components\TextInput::make('opacidade')
                    ->label('Opacidade (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(100),
            ])->columns(2),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Toggle::make('ativo')->label('Ativa')->default(true),
                Forms\Components\TextInput::make('ordem')->label('Ordem de exibição')->numeric()->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Fonte')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('categoria.nome')->label('Categoria')->placeholder('— (sem categoria)')->sortable(),
                Tables\Columns\TextColumn::make('camadas')->label('Camadas')->limit(30),
                Tables\Columns\IconColumn::make('ativo')->label('Ativa')->boolean(),
                Tables\Columns\TextColumn::make('ordem')->label('Ordem')->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFonteWms::route('/')];
    }
}
