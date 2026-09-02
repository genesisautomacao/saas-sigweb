<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\TemCoordenadaMob;
use App\Filament\Resources\MobZonaResource\Pages;
use App\Models\MobZona;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Zonas de estudo da mobilidade (docs/piuma.txt §2.6, Onda 3): zonas O/D,
 * quadrantes, polo industrial e setores censitários IBGE.
 */
class MobZonaResource extends Resource
{
    use HasTenantModule;
    use TemCoordenadaMob;

    protected static ?string $tenantModule = 'mob_infra';

    protected static ?string $model = MobZona::class;

    protected static ?string $tenantRelationshipName = 'mobZonas';

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'Mobilidade Urbana';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Zona de Estudo';

    protected static ?string $pluralModelLabel = 'Zonas de Estudo';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tipo')
                ->label('Tipo')
                ->options(MobZona::TIPOS)
                ->live()
                ->required(),
            Forms\Components\TextInput::make('name')->label('Nome')->maxLength(255),
            Forms\Components\TextInput::make('codigo')
                ->label('Código (setor IBGE)')
                ->maxLength(50)
                ->visible(fn (Forms\Get $get) => $get('tipo') === 'setor_censitario'),
            Forms\Components\Select::make('situacao')
                ->label('Situação')
                ->options(['Urbana' => 'Urbana', 'Rural' => 'Rural'])
                ->visible(fn (Forms\Get $get) => $get('tipo') === 'setor_censitario'),
            Forms\Components\TextInput::make('origens')
                ->label('% Origens (O/D)')
                ->numeric()
                ->visible(fn (Forms\Get $get) => $get('tipo') === 'zona_od'),
            Forms\Components\TextInput::make('destinos')
                ->label('% Destinos (O/D)')
                ->numeric()
                ->visible(fn (Forms\Get $get) => $get('tipo') === 'zona_od'),
            static::campoCoordenada('mob_zonas'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => static::comCoordenada($query, 'mob_zonas'))
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => MobZona::TIPOS[$state] ?? ($state ?? '—'))
                    ->color(fn (?string $state) => match ($state) {
                        'zona_od' => 'info',
                        'quadrante' => 'warning',
                        'polo_industrial' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('codigo')->label('Código IBGE')->searchable()->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('origens')->label('% Orig.')->numeric(2, ',', '.')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('destinos')->label('% Dest.')->numeric(2, ',', '.')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('area_geo')
                    ->label('Área')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state / 1000000, 3, ',', '.').' km²' : '—')
                    ->sortable(),
                static::colunaCoordenada(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(MobZona::TIPOS),
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
        return ['index' => Pages\ListMobZonas::route('/')];
    }
}
