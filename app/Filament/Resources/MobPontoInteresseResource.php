<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\TemCoordenadaMob;
use App\Filament\Resources\MobPontoInteresseResource\Pages;
use App\Models\MobPontoInteresse;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Pontos de interesse da mobilidade (docs/piuma.txt §2.3, Onda 3). */
class MobPontoInteresseResource extends Resource
{
    use HasTenantModule;
    use TemCoordenadaMob;

    protected static ?string $tenantModule = 'mob_infra';

    protected static ?string $model = MobPontoInteresse::class;

    protected static ?string $tenantRelationshipName = 'mobPontosInteresse';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Mobilidade Urbana';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Ponto de Interesse';

    protected static ?string $pluralModelLabel = 'Pontos de Interesse';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('categoria')
                ->label('Categoria')
                ->options(MobPontoInteresse::CATEGORIAS)
                ->required(),
            Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(255),
            Forms\Components\TextInput::make('numero')->label('Número / referência')->maxLength(50),
            static::campoCoordenada('mob_pontos_interesse'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => static::comCoordenada($query, 'mob_pontos_interesse'))
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('categoria')
                    ->label('Categoria')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => MobPontoInteresse::CATEGORIAS[$state] ?? ($state ?? '—'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('numero')->label('Número')->placeholder('—'),
                static::colunaCoordenada(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categoria')
                    ->label('Categoria')
                    ->options(MobPontoInteresse::CATEGORIAS),
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
        return ['index' => Pages\ListMobPontosInteresse::route('/')];
    }
}
