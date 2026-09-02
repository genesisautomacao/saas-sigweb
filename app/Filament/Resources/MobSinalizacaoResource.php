<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\TemCoordenadaMob;
use App\Filament\Resources\MobSinalizacaoResource\Pages;
use App\Models\MobSinalizacao;
use App\Models\MobTipoSinalizacao;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sinalização viária (Mobilidade Urbana — docs/piuma.txt, Onda 3).
 * A placa aponta pro CATÁLOGO (decisão 6.1); posição é editada no mapa.
 */
class MobSinalizacaoResource extends Resource
{
    use HasTenantModule;
    use TemCoordenadaMob;

    protected static ?string $tenantModule = 'mob_infra';

    protected static ?string $model = MobSinalizacao::class;

    protected static ?string $tenantRelationshipName = 'mobSinalizacoes';

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Mobilidade Urbana';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Sinalização';

    protected static ?string $pluralModelLabel = 'Sinalizações';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tipo_sinalizacao_id')
                ->label('Tipo (catálogo)')
                ->relationship(
                    'tipoSinalizacao',
                    'name',
                    fn ($query) => $query->where('ativo', true)->orderBy('tipo')->orderBy('ordem'),
                )
                ->getOptionLabelFromRecordUsing(fn (MobTipoSinalizacao $t) => $t->name.' ('.(MobTipoSinalizacao::TIPOS[$t->tipo] ?? $t->tipo).')')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Textarea::make('observacao')->label('Observação')->rows(2)->columnSpanFull(),
            Forms\Components\Placeholder::make('descricao_original')
                ->label('Texto original da coleta')
                ->content(fn (?MobSinalizacao $record) => $record?->descricao_original ?: '—')
                ->visible(fn (?MobSinalizacao $record) => filled($record?->descricao_original))
                ->columnSpanFull(),
            static::campoCoordenada('mob_sinalizacoes'),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => static::comCoordenada($query, 'mob_sinalizacoes'))
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('tipoSinalizacao.name')
                    ->label('Tipo (catálogo)')
                    ->badge()
                    ->color(fn ($record) => \Filament\Support\Colors\Color::hex($record->tipoSinalizacao?->cor ?: '#9CA3AF'))
                    ->placeholder('A Classificar')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipoSinalizacao.tipo')
                    ->label('V/H')
                    ->formatStateUsing(fn (?string $state) => $state ? (MobTipoSinalizacao::TIPOS[$state] ?? $state) : '—'),
                Tables\Columns\TextColumn::make('tipoSinalizacao.codigo_ctb')->label('CTB')->placeholder('—'),
                static::colunaCoordenada(),
                Tables\Columns\TextColumn::make('descricao_original')
                    ->label('Texto da coleta')
                    ->limit(45)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('observacao')->label('Observação')->limit(30)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_sinalizacao_id')
                    ->label('Tipo (catálogo)')
                    ->options(fn () => MobTipoSinalizacao::query()->orderBy('tipo')->orderBy('ordem')
                        ->get()
                        ->mapWithKeys(fn ($t) => [$t->id => $t->name.' ('.(MobTipoSinalizacao::TIPOS[$t->tipo] ?? $t->tipo).')'])
                        ->all())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('vh')
                    ->label('Vertical / Horizontal')
                    ->options(MobTipoSinalizacao::TIPOS)
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('tipoSinalizacao', fn ($q) => $q->where('tipo', $data['value']))
                        : $query),
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
        return ['index' => Pages\ListMobSinalizacoes::route('/')];
    }
}
