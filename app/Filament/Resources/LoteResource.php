<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoteResource\Pages;
use App\Models\Lote;
use App\Models\Quadra;
use App\Models\Zona;
use App\Services\Irregularidade\NotificacaoIrregularidadeService;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoteResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'imobiliario'; // O módulo agora é outro!

    protected static ?string $model = Lote::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Módulo Imobiliário';
    protected static ?string $modelLabel = 'Lote (Terreno)';
    protected static ?string $pluralModelLabel = 'Lotes';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Localização e Identificação')
                    ->schema([

                        Forms\Components\TextInput::make('numero_lote')
                            ->label('Número do Lote (Planta)')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('main_facade_length')
                            ->label('Testada Principal / Frente (metros)')
                            ->numeric()
                            ->suffix('m'),

                        Forms\Components\TextInput::make('area_geo')
                            ->label('Área Geo / PostGIS (m²)')
                            ->numeric()
                            ->suffix('m²')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('area_cadastrada')
                            ->label('Área Cadastral / Tributário (m²)')
                            ->numeric()
                            ->suffix('m²')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Preenchida automaticamente via tributario:importar'),

                        Forms\Components\Select::make('quadra_id')
                            ->label('Quadra Pertencente')
                            ->options(fn() => Quadra::pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('zona_id')
                            ->label('Zona Urbana (Para Viabilidade)')
                            ->options(fn() => Zona::pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('ocupacao')
                            ->label('Ocupação do Lote')
                            ->options([
                                'baldio'     => 'Baldio',
                                'construido' => 'Construído',
                            ])
                            ->placeholder('Selecione...')
                            ->nullable(),

                        Forms\Components\Select::make('situacao_quadra')
                            ->label('Situação na Quadra')
                            ->options([
                                'meio_quadra' => 'Meio de Quadra',
                                'esquina'     => 'Esquina',
                                'encravado'   => 'Encravado',
                            ])
                            ->placeholder('Selecione...')
                            ->nullable(),

                    ])->columns(3),

                Forms\Components\Section::make('Endereço (Numeração Predial)')
                    ->description('Herdado do cadastro tributário (dados_tributarios) e atualizado pelo gerador de numeração predial no mapa.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\TextInput::make('tipo_logradouro')
                            ->label('Tipo de Logradouro')
                            ->placeholder('Rua, Avenida...')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('logradouro')
                            ->label('Logradouro')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('numero_logradouro')
                            ->label('Número Predial (atual)')
                            ->helperText('Definido pelo gerador de numeração predial.')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('cep')
                            ->label('CEP')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('numero_predial_antigo')
                            ->label('Número Predial Anterior')
                            ->helperText('Preenchido automaticamente ao salvar uma nova numeração.')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(3),

                Forms\Components\Section::make('Dados de Vistoria de Campo')
                    ->description('Preenchidos automaticamente pelo agente mobile, editáveis pelo supervisor.')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        Forms\Components\Select::make('status_cadastro')
                            ->label('Status do Cadastro')
                            ->options([
                                'nao_visitado'   => 'Não Visitado',
                                'coletado'       => 'Coletado',
                                'pendente'       => 'Pendente',
                                'inconformidade' => 'Inconformidade',
                            ])
                            ->default('nao_visitado')
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\FileUpload::make('foto_frontal')
                                ->label('Foto Frontal')
                                ->image()
                                ->imageEditor()
                                ->disk('public')
                                ->directory('lotes_fotos')
                                ->maxSize(5120)
                                ->nullable(),

                            Forms\Components\FileUpload::make('foto_lateral_esq')
                                ->label('Lateral Esquerda')
                                ->image()
                                ->imageEditor()
                                ->disk('public')
                                ->directory('lotes_fotos')
                                ->maxSize(5120)
                                ->nullable(),

                            Forms\Components\FileUpload::make('foto_lateral_dir')
                                ->label('Lateral Direita')
                                ->image()
                                ->imageEditor()
                                ->disk('public')
                                ->directory('lotes_fotos')
                                ->maxSize(5120)
                                ->nullable(),
                        ])->columnSpanFull(),

                        Forms\Components\Textarea::make('observacao')
                            ->label('Observação Geral')
                            ->rows(3)
                            ->nullable()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('inconformidade_descricao')
                            ->label('Descrição da Inconformidade')
                            ->rows(3)
                            ->nullable()
                            ->visible(fn (Forms\Get $get) => $get('status_cadastro') === 'inconformidade')
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('dados_vistoria')
                            ->label('Boletim de Campo (Dados Livres)')
                            ->keyLabel('Campo')
                            ->valueLabel('Valor')
                            ->reorderable()
                            ->nullable()
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('coletado_por_id')
                                ->label('Coletado por')
                                ->relationship('coletor', 'name')
                                ->disabled(),
                            Forms\Components\DateTimePicker::make('coletado_em')
                                ->label('Coletado em')
                                ->disabled(),
                        ])->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Geometria do Lote (Polígono)')
                    ->description('Caso não desenhado no mapa, insira o GeoJSON gerado pela topografia.')
                    ->schema([
                        Forms\Components\Textarea::make('geo_json_input')
                            ->label('Coordenadas do Perímetro (GeoJSON)')
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
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('numero_lote')
                    ->label('Lote nº')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status_cadastro')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'nao_visitado'   => 'Não Visitado',
                        'coletado'       => 'Coletado',
                        'pendente'       => 'Pendente',
                        'inconformidade' => 'Inconformidade',
                        default          => '—',
                    })
                    ->color(fn($state) => match($state) {
                        'coletado'       => 'success',
                        'pendente'       => 'warning',
                        'inconformidade' => 'danger',
                        default          => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('unidadesImobiliarias.codigo_imovel_tributario')
                    ->label('Códigos Fiscais (Unidades)')
                    ->badge() // Transforma os códigos em "etiquetas" visuais
                    ->color('success') // Cor verdinha pra destacar
                    ->searchable() // <-- A MÁGICA DA PESQUISA ESTÁ AQUI!
                    ->default('Nenhum'),

                Tables\Columns\TextColumn::make('logradouro')
                    ->label('Logradouro')
                    ->formatStateUsing(fn(?string $state, Lote $record) => trim(($record->tipo_logradouro ? $record->tipo_logradouro . ' ' : '') . ($state ?? '')) ?: '—')
                    ->searchable(['tipo_logradouro', 'logradouro'])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('numero_logradouro')
                    ->label('Nº Predial')
                    ->searchable()
                    ->default('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('cep')
                    ->label('CEP')
                    ->searchable()
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('quadra.name')
                    ->label('Quadra')
                    ->sortable(),

                Tables\Columns\TextColumn::make('zona.sigla')
                    ->label('Zona')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('area_geo')
                    ->label('Área Geo (m²)')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->suffix(' m²')
                    ->sortable(),

                Tables\Columns\TextColumn::make('delta_area')
                    ->label('Δ Área (%)')
                    ->getStateUsing(function (Lote $record): ?string {
                        if (!$record->area_cadastrada || !$record->area_geo) return null;
                        $delta = (($record->area_geo - $record->area_cadastrada) / $record->area_cadastrada) * 100;
                        return ($delta >= 0 ? '+' : '') . number_format($delta, 1, ',', '.') . '%';
                    })
                    ->badge()
                    ->color(function (Lote $record): string {
                        if (!$record->area_cadastrada || !$record->area_geo) return 'gray';
                        $delta = abs(($record->area_geo - $record->area_cadastrada) / $record->area_cadastrada * 100);
                        return $delta > 5 ? 'danger' : 'success';
                    })
                    ->default('—')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('ocupacao')
                    ->label('Ocupação')
                    ->formatStateUsing(fn($state) => match($state) {
                        'baldio'     => 'Baldio',
                        'construido' => 'Construído',
                        default      => '—',
                    })
                    ->color(fn($state) => match($state) {
                        'baldio'     => 'warning',
                        'construido' => 'success',
                        default      => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('situacao_quadra')
                    ->label('Situação')
                    ->formatStateUsing(fn($state) => match($state) {
                        'meio_quadra' => 'Meio de Quadra',
                        'esquina'     => 'Esquina',
                        'encravado'   => 'Encravado',
                        default       => '—',
                    })
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('quadra_id')
                    ->label('Filtrar por Quadra')
                    ->relationship('quadra', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('zona_id')
                    ->label('Filtrar por Zona')
                    ->relationship('zona', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status_cadastro')
                    ->label('Status do Cadastro')
                    ->options([
                        'nao_visitado'   => 'Não Visitado',
                        'coletado'       => 'Coletado',
                        'pendente'       => 'Pendente',
                        'inconformidade' => 'Inconformidade',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('divergencia_area')
                    ->label('Divergência de Área')
                    ->form([
                        Forms\Components\TextInput::make('percentual')
                            ->label('Divergência mínima (%)')
                            ->numeric()
                            ->default(5)
                            ->suffix('%'),
                    ])
                    ->query(function ($query, array $data) {
                        $pct = isset($data['percentual']) ? (float) $data['percentual'] / 100 : null;
                        if ($pct === null) return $query;
                        return $query
                            ->whereNotNull('area_cadastrada')
                            ->where('area_cadastrada', '>', 0)
                            ->whereNotNull('area_geo')
                            ->whereRaw('ABS(area_geo - area_cadastrada) / area_cadastrada > ?', [$pct]);
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!isset($data['percentual'])) return null;
                        return 'Divergência > ' . $data['percentual'] . '%';
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function (Lote $record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if ($record->geo_json && isset($record->geo_json->coordinates[0][0][0])) {
                            $lon = $record->geo_json->coordinates[0][0][0][0];
                            $lat = $record->geo_json->coordinates[0][0][0][1];
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=lotes&focus_lat=' . $lat . '&focus_lon=' . $lon);
                        }
                        return null;
                    })
                    ->visible(fn(Lote $record) => $record->geo_json !== null),

                Tables\Actions\Action::make('emitir_notificacao')
                    ->label('Notificação')
                    ->icon('heroicon-o-document-text')
                    ->color('danger')
                    ->tooltip('Emitir Notificação de Irregularidade')
                    ->visible(fn (Lote $record) => !empty($record->inconformidade_descricao))
                    ->action(fn (Lote $record) => app(NotificacaoIrregularidadeService::class)->generatePdf($record)),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            LoteResource\RelationManagers\UnidadesImobiliariasRelationManager::class,
            LoteResource\RelationManagers\EdificacoesRelationManager::class,
            LoteResource\RelationManagers\TestadasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLotes::route('/'),
            'create' => Pages\CreateLote::route('/create'),
            'edit' => Pages\EditLote::route('/{record}/edit'),
        ];
    }
}