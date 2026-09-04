<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\TemCoordenadaMob;
use App\Filament\Resources\MobViaResource\Pages;
use App\Models\MobVia;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vias urbanas (Mobilidade Urbana — docs/piuma.txt, Onda 6): a entidade do
 * FLUXO (sentido mão única/dupla + direção = ordem dos vértices). CRUD modal
 * dos dados tabulares; geometria, inverter e caneta de sentidos ficam no MAPA.
 */
class MobViaResource extends Resource
{
    use HasTenantModule;
    use TemCoordenadaMob;

    protected static ?string $tenantModule = 'mob_infra';

    protected static ?string $model = MobVia::class;

    protected static ?string $tenantRelationshipName = 'mobVias';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Mobilidade Urbana';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Via Urbana';

    protected static ?string $pluralModelLabel = 'Vias Urbanas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Via')->schema([
                static::campoCoordenada('mob_vias'),
                Forms\Components\TextInput::make('nome')
                    ->label('Nome (opcional)')
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\Select::make('sentido')
                    ->label('Sentido')
                    ->options(MobVia::SENTIDOS)
                    ->placeholder('Não classificado')
                    ->helperText('Mão única segue a direção do desenho da linha (inverter/desenhar: no mapa).')
                    ->nullable(),
                Forms\Components\Select::make('logradouro_id')
                    ->label('Logradouro (opcional)')
                    ->relationship('logradouro', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => static::comCoordenada($query, 'mob_vias')->withCount('trechos'))
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('sentido')
                    ->label('Sentido')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? (MobVia::SENTIDOS[$state] ?? $state) : 'Não classificado')
                    ->color(fn (?string $state) => match ($state) {
                        'mao_unica' => 'info',
                        'mao_dupla' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('Não classificado'),
                Tables\Columns\TextColumn::make('trechos_count')
                    ->label('Trechos')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
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
                Tables\Filters\SelectFilter::make('sentido')
                    ->label('Sentido')
                    ->options(MobVia::SENTIDOS + ['nao_classificado' => 'Não classificado'])
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'nao_classificado' => $query->whereNull('sentido'),
                            null, '' => $query,
                            default => $query->where('sentido', $data['value']),
                        };
                    }),
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
        return ['index' => Pages\ListMobVias::route('/')];
    }
}
