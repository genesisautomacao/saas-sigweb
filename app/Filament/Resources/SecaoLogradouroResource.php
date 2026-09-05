<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SecaoLogradouroResource\Pages;
use App\Models\SecaoLogradouro;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SecaoLogradouroResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'base_cartografica'; // D8 (2026-09-05): a seção segue o logradouro

    protected static ?string $model = SecaoLogradouro::class;

    protected static ?string $navigationIcon = 'heroicon-o-minus';

    protected static ?string $navigationGroup = 'Base Cartográfica';

    protected static ?int $navigationSort = 4; // logo abaixo de Logradouros (3)

    protected static ?string $navigationLabel = 'Seções de Logradouro';

    protected static ?string $modelLabel = 'Seção de Logradouro';

    protected static ?string $pluralModelLabel = 'Seções de Logradouro';

    protected static ?string $slug = 'secoes-logradouro';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')->schema([
                Forms\Components\TextInput::make('codigo')
                    ->label('Código da Seção (métrico)')
                    ->helperText('Código do cadastro municipal. Único por logradouro + lado.')
                    ->maxLength(50)
                    // T1.3 — validação amigável da unicidade código+lado (o índice do banco é a trava final)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule, \Filament\Forms\Get $get) => $rule
                            ->where('tenant_id', \Filament\Facades\Filament::getTenant()?->id)
                            ->where('logradouro_id', $get('logradouro_id'))
                            ->where('lado', $get('lado'))
                            ->whereNull('deleted_at'),
                    )
                    ->validationMessages(['unique' => 'Já existe uma seção com este código e este lado neste logradouro.']),

                // Item 44 do edital — lista governada pelo sistema (rótulo white-label)
                \App\Services\Coleta\CampoDominioService::aplicar(
                    Forms\Components\Select::make('lado')->placeholder('Selecione...')->nullable()->live(),
                    'secao_logradouro', 'lado'
                ),

                Forms\Components\TextInput::make('name')
                    ->label('Nome / Identificação da Seção')
                    ->maxLength(255),
                Forms\Components\Select::make('logradouro_id')
                    ->label('Logradouro')
                    ->relationship('logradouro', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
            ])->columns(3),

            // Refatoração PoC Tangará: tipo_pavimentacao virou campo customizado (kit)
            Forms\Components\Section::make('Dados da Seção')
                ->visible(fn () => \App\Services\Coleta\CampoCustomizadoService::definicoes('secao_logradouro')->isNotEmpty())
                ->schema(fn () => \App\Services\Coleta\CampoCustomizadoService::componentes('secao_logradouro'))
                ->columns(3),

            // T1.7 (item 17) — "dados dos logradouros, inclusive com as IMAGENS das Seções".
            // Reusa a tabela polimórfica `documentos` (padrão da UnidadeImobiliaria).
            Forms\Components\Section::make('Fotos da Seção')
                ->schema([
                    Forms\Components\Repeater::make('fotos')
                        ->relationship('fotos')
                        ->hiddenLabel()
                        ->schema([
                            Forms\Components\Hidden::make('type')->default('Foto'),
                            Forms\Components\TextInput::make('name')
                                ->label('Legenda')
                                ->placeholder('Ex: Vista do início do trecho')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\FileUpload::make('path')
                                ->label('Imagem')
                                ->directory('secoes_logradouro/fotos')
                                ->image()
                                ->imageEditor()
                                ->openable()
                                ->downloadable()
                                ->maxSize(5120)
                                ->required(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Adicionar Foto')
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                ]),

            Forms\Components\Section::make('Dados Espaciais')->schema([
                Forms\Components\Textarea::make('geo_json_input')
                    ->label('Coordenadas (GeoJSON LineString)')
                    ->helperText('Cole um GeoJSON de LineString ou uma lista de coordenadas separadas por vírgula (lon lat).')
                    ->rows(10)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->description(fn ($record) => $record->codigo_composto && $record->codigo_composto !== $record->codigo
                        ? 'Completo: '.$record->codigo_composto
                        : null)
                    ->searchable()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('logradouro.name')->label('Logradouro')->searchable(),
                Tables\Columns\TextColumn::make('lado')
                    ->label('Lado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => \App\Services\Coleta\CampoDominioService::rotuloValor('secao_logradouro', 'lado', $state) ?? '—'),
                Tables\Columns\TextColumn::make('dados_customizados.tipo_pavimentacao')
                    ->label('Pavimentação')
                    ->badge()
                    ->default('—'),
                Tables\Columns\TextColumn::make('extensao_geo')
                    ->label('Extensão (m)')
                    ->numeric(2, ',', '.')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function ($record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        $row = \Illuminate\Support\Facades\DB::table('secoes_logradouro')
                            ->selectRaw('ST_X(ST_PointOnSurface(geo)) AS lon, ST_Y(ST_PointOnSurface(geo)) AS lat')
                            ->where('id', $record->id)
                            ->first();
                        if ($row && $row->lat && $row->lon) {
                            return url('/app/'.$tenant->slug.'/mapa-interativo?layer=secoes_logradouro&focus_lat='.$row->lat.'&focus_lon='.$row->lon.'&zoom=18');
                        }

                        return null;
                    })
                    ->visible(fn ($record) => $record->geo_json !== null),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListSecoesLogradouro::route('/'),
            'create' => Pages\CreateSecaoLogradouro::route('/create'),
            'edit' => Pages\EditSecaoLogradouro::route('/{record}/edit'),
        ];
    }
}
