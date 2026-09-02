<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\TemCoordenadaMob;
use App\Filament\Resources\MobFluxoResource\Pages;
use App\Models\MobFluxo;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fluxos Origem/Destino (docs/piuma.txt §2.7, Onda 3) — linhas de desejo.
 * Fluxo INTRAZONAL (origem = destino) não tem linha no mapa por natureza:
 * o levantamento entrega só o volume (badge "Intrazonal" na tabela).
 */
class MobFluxoResource extends Resource
{
    use HasTenantModule;
    use TemCoordenadaMob;

    protected static ?string $tenantModule = 'mob_infra';

    protected static ?string $model = MobFluxo::class;

    protected static ?string $tenantRelationshipName = 'mobFluxos';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Mobilidade Urbana';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Fluxo O/D';

    protected static ?string $pluralModelLabel = 'Fluxos Origem/Destino';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('destino')
                ->label('Região de destino')
                ->options(MobFluxo::DESTINOS)
                ->required(),
            Forms\Components\TextInput::make('valores')
                ->label('Volume de deslocamentos')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            static::campoCoordenada('mob_fluxos'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => static::comCoordenada(
                $query, 'mob_fluxos', ', (geo IS NULL OR ST_IsEmpty(geo)) AS intrazonal',
            ))
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('destino')
                    ->label('Destino')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => MobFluxo::DESTINOS[$state] ?? ($state ?? '—'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('valores')
                    ->label('Volume')
                    ->numeric()
                    ->sortable(),
                static::colunaCoordenada(),
                Tables\Columns\IconColumn::make('intrazonal')
                    ->label('Intrazonal')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-uturn-left')
                    ->falseIcon('heroicon-o-arrow-long-right')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->tooltip('Intrazonal = origem e destino na mesma região (sem linha no mapa)'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('destino')->label('Destino')->options(MobFluxo::DESTINOS),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('valores', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMobFluxos::route('/')];
    }
}
