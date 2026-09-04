<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\TemCoordenadaMob;
use App\Filament\Resources\MobTrechoResource\Pages;
use App\Models\MobTrecho;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trechos viários (Mobilidade Urbana — docs/piuma.txt, Onda 3).
 * CRUD modal; geometria é editada no MAPA (a direção é a ordem dos vértices =
 * direção do mapeamento — aqui só os dados tabulares). Sentido de tráfego é
 * da Via Urbana (MobViaResource), não do trecho.
 */
class MobTrechoResource extends Resource
{
    use HasTenantModule;
    use TemCoordenadaMob;

    protected static ?string $tenantModule = 'mob_infra';

    protected static ?string $model = MobTrecho::class;

    protected static ?string $tenantRelationshipName = 'mobTrechos';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-long-right';

    protected static ?string $navigationGroup = 'Mobilidade Urbana';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Trecho Viário';

    protected static ?string $pluralModelLabel = 'Trechos Viários';

    protected static function rotuloVia(\App\Models\MobVia $v): string
    {
        return $v->rotulo().($v->sentido ? ' — '.(\App\Models\MobVia::SENTIDOS[$v->sentido] ?? $v->sentido) : ' — sentido não classificado');
    }

    protected static function selectVocabulario(string $campo, string $label): Forms\Components\Select
    {
        return Forms\Components\Select::make($campo)
            ->label($label)
            ->options(array_combine(MobTrecho::VOCABULARIOS[$campo], MobTrecho::VOCABULARIOS[$campo]))
            ->placeholder('Selecione...')
            ->nullable();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Via')->schema([
                static::campoCoordenada('mob_trechos'),
                Forms\Components\Select::make('via_id')
                    ->label('Via urbana (fluxo)')
                    ->options(fn () => \App\Models\MobVia::query()->orderBy('sequential_id')->limit(500)->get()
                        ->mapWithKeys(fn ($v) => [$v->id => static::rotuloVia($v)]))
                    ->getOptionLabelUsing(fn ($value) => ($v = \App\Models\MobVia::find($value)) ? static::rotuloVia($v) : null)
                    ->getSearchResultsUsing(fn (string $search) => \App\Models\MobVia::query()
                        ->where(fn ($q) => $q->where('nome', 'ilike', "%{$search}%")
                            ->orWhereRaw('CAST(sequential_id AS TEXT) LIKE ?', ["%{$search}%"]))
                        ->orderBy('sequential_id')->limit(50)->get()
                        ->mapWithKeys(fn ($v) => [$v->id => static::rotuloVia($v)]))
                    ->searchable()
                    ->helperText('Via de tráfego que este trecho de levantamento compõe (1:1 em Piúma).')
                    ->nullable(),
                static::selectVocabulario('tipologia_da_via', 'Tipologia da via'),
                static::selectVocabulario('classe_faixa_rodagem', 'Classe da faixa de rodagem'),
                static::selectVocabulario('tipo_de_pavimentacao', 'Pavimentação'),
                static::selectVocabulario('estado_conservacao_pavimentacao', 'Estado da pavimentação'),
                static::selectVocabulario('dimensionamento_da_via', 'Largura da via'),
                Forms\Components\Select::make('logradouro_id')
                    ->label('Logradouro (opcional)')
                    ->relationship('logradouro', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
            ])->columns(3),

            Forms\Components\Section::make('Levantamento (calçadas, estacionamento, vegetação)')
                ->visible(fn () => \App\Services\Coleta\CampoCustomizadoService::definicoes('mob_trecho')->isNotEmpty())
                ->schema(fn () => \App\Services\Coleta\CampoCustomizadoService::componentes('mob_trecho'))
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => static::comCoordenada($query, 'mob_trechos'))
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('via.sequential_id')
                    ->label('Via')
                    ->formatStateUsing(fn ($record) => $record->via?->rotulo())
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tipologia_da_via')->label('Tipologia')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('tipo_de_pavimentacao')->label('Pavimentação')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('estado_conservacao_pavimentacao')->label('Estado')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('extensao_geo')
                    ->label('Extensão')
                    ->numeric(2, ',', '.')
                    ->suffix(' m')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('azimute')
                    ->label('Azimute')
                    ->numeric(1, ',', '.')
                    ->suffix('°')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                static::colunaCoordenada(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipologia_da_via')
                    ->label('Tipologia')
                    ->options(array_combine(MobTrecho::VOCABULARIOS['tipologia_da_via'], MobTrecho::VOCABULARIOS['tipologia_da_via'])),
                Tables\Filters\SelectFilter::make('tipo_de_pavimentacao')
                    ->label('Pavimentação')
                    ->options(array_combine(MobTrecho::VOCABULARIOS['tipo_de_pavimentacao'], MobTrecho::VOCABULARIOS['tipo_de_pavimentacao'])),
                Tables\Filters\SelectFilter::make('estado_conservacao_pavimentacao')
                    ->label('Estado da pavimentação')
                    ->options(array_combine(MobTrecho::VOCABULARIOS['estado_conservacao_pavimentacao'], MobTrecho::VOCABULARIOS['estado_conservacao_pavimentacao'])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('sequential_id');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMobTrechos::route('/')];
    }
}
