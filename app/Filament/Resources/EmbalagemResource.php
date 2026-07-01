<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmbalagemResource\Pages;
use App\Models\Embalagem;
use App\Models\UnidadeMedida;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmbalagemResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'estoque';

    protected static ?string $model = Embalagem::class;
    protected static ?string $slug = 'embalagens';
    protected static ?string $tenantRelationshipName = 'embalagens';
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Estoque e Almoxarifado';
    protected static ?string $modelLabel = 'Embalagem';
    protected static ?string $pluralModelLabel = 'Embalagens';
    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome (ex.: Caixa com 12)')->required()->maxLength(255),
            Forms\Components\TextInput::make('quantidade')->label('Quantidade contida')->numeric()->default(1)->required(),
            Forms\Components\Select::make('unidade_medida_id')
                ->label('Unidade de Medida')
                ->options(fn() => UnidadeMedida::pluck('name', 'id'))
                ->searchable(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Embalagem')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('quantidade')->label('Qtd')->numeric(decimalPlaces: 3),
                Tables\Columns\TextColumn::make('unidadeMedida.sigla')->label('UM')->badge()->default('—'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListEmbalagens::route('/')];
    }
}
