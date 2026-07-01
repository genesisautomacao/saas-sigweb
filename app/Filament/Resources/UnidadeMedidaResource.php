<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnidadeMedidaResource\Pages;
use App\Models\UnidadeMedida;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UnidadeMedidaResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'estoque';

    protected static ?string $model = UnidadeMedida::class;
    protected static ?string $tenantRelationshipName = 'unidadeMedidas';
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Estoque e Almoxarifado';
    protected static ?string $modelLabel = 'Unidade de Medida';
    protected static ?string $pluralModelLabel = 'Unidades de Medida';
    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome (ex.: Unidade, Metro)')->required()->maxLength(255),
            Forms\Components\TextInput::make('sigla')->label('Sigla (ex.: UN, M, CX)')->required()->maxLength(20),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sigla')->label('Sigla')->searchable()->badge(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUnidadeMedidas::route('/')];
    }
}
