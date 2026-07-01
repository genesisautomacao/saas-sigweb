<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoEntidadeResource\Pages;
use App\Models\TipoEntidade;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TipoEntidadeResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'social';
    protected static ?string $tenantRelationshipName = 'tipoEntidades';

    protected static ?string $model = TipoEntidade::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'Módulo Social';
    protected static ?string $modelLabel = 'Tipo de Entidade';
    protected static ?string $pluralModelLabel = 'Tipos de Entidade';
    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome (ex.: CRAS, CREAS, ONG)')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Tipo de Entidade')->searchable()->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTipoEntidades::route('/')];
    }
}
