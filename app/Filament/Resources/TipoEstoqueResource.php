<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoEstoqueResource\Pages;
use App\Models\TipoEstoque;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TipoEstoqueResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'estoque';

    protected static ?string $model = TipoEstoque::class;
    protected static ?string $tenantRelationshipName = 'tipoEstoques';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Estoque e Almoxarifado';
    protected static ?string $modelLabel = 'Tipo de Estoque';
    protected static ?string $pluralModelLabel = 'Tipos de Estoque';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome (ex.: Almoxarifado, Danificado)')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->label('Descrição')->rows(3)->columnSpanFull(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Tipo de Estoque')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('description')->label('Descrição')->limit(50),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTipoEstoques::route('/')];
    }
}
