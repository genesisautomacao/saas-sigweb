<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EstabelecimentoResource\Pages;
use App\Models\Estabelecimento;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EstabelecimentoResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'estoque';

    protected static ?string $model = Estabelecimento::class;
    protected static ?string $tenantRelationshipName = 'estabelecimentos';
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Estoque e Almoxarifado';
    protected static ?string $modelLabel = 'Estabelecimento';
    protected static ?string $pluralModelLabel = 'Estabelecimentos';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(255),
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
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('cnpj')->label('CNPJ')->searchable(),
                Tables\Columns\TextColumn::make('telefone')->label('Telefone'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListEstabelecimentos::route('/')];
    }
}
