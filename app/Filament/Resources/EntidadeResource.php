<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EntidadeResource\Pages;
use App\Models\Entidade;
use App\Models\TipoEntidade;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EntidadeResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'social';
    protected static ?string $tenantRelationshipName = 'entidades';

    protected static ?string $model = Entidade::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Módulo Social';
    protected static ?string $modelLabel = 'Entidade';
    protected static ?string $pluralModelLabel = 'Entidades';
    protected static ?int $navigationSort = 22;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(255),
            Forms\Components\Select::make('tipo_entidade_id')->label('Tipo de Entidade')
                ->options(fn() => TipoEntidade::pluck('name', 'id'))->searchable(),
            Forms\Components\TextInput::make('cnpj')->label('CNPJ')->maxLength(255),
            Forms\Components\TextInput::make('telefone')->label('Telefone')->maxLength(255),
            Forms\Components\TextInput::make('email')->label('E-mail')->email()->maxLength(255),
            Forms\Components\TextInput::make('endereco')->label('Endereço')->maxLength(255)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Entidade')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tipoEntidade.name')->label('Tipo')->badge()->default('—'),
                Tables\Columns\TextColumn::make('telefone')->label('Telefone'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListEntidades::route('/')];
    }
}
