<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\TemCoordenadaMob;
use App\Filament\Resources\MobEixoResource\Pages;
use App\Models\MobEixo;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eixos de mobilidade (docs/piuma.txt §2.5, Onda 3): ciclovias, eixos
 * comerciais, rotas de carga e rodovias. Extensão em METROS, exibida em km.
 */
class MobEixoResource extends Resource
{
    use HasTenantModule;
    use TemCoordenadaMob;

    protected static ?string $tenantModule = 'mob_infra';

    protected static ?string $model = MobEixo::class;

    protected static ?string $tenantRelationshipName = 'mobEixos';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationGroup = 'Mobilidade Urbana';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Eixo de Mobilidade';

    protected static ?string $pluralModelLabel = 'Eixos de Mobilidade';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tipo')
                ->label('Tipo')
                ->options(MobEixo::TIPOS)
                ->required(),
            Forms\Components\TextInput::make('name')->label('Nome')->maxLength(255),
            static::campoCoordenada('mob_eixos'),

            Forms\Components\Section::make('Dados complementares')
                ->visible(fn () => \App\Services\Coleta\CampoCustomizadoService::definicoes('mob_eixo')->isNotEmpty())
                ->schema(fn () => \App\Services\Coleta\CampoCustomizadoService::componentes('mob_eixo'))
                ->columns(3),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => static::comCoordenada($query, 'mob_eixos'))
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => MobEixo::TIPOS[$state] ?? ($state ?? '—'))
                    ->color(fn (?string $state) => match ($state) {
                        'ciclovia' => 'success',
                        'eixo_comercial' => 'warning',
                        'rota_carga' => 'info',
                        'rodovia' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('extensao_geo')
                    ->label('Extensão')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state / 1000, 2, ',', '.').' km' : '—')
                    ->sortable(),
                static::colunaCoordenada(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(MobEixo::TIPOS),
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
        return ['index' => Pages\ListMobEixos::route('/')];
    }
}
