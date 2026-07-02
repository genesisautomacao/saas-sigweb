<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PgvCubResource\Pages;
use App\Models\PgvCub;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PgvCubResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'pgv';
    protected static ?string $tenantRelationshipName = 'pgvCubs';

    protected static ?string $model = PgvCub::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Gestão Tributária (PGV)';
    protected static ?string $modelLabel = 'CUB';
    protected static ?string $pluralModelLabel = 'Tabela CUB';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('tipologia')->label('Tipologia (ex.: Residencial, Comercial)')->required()->maxLength(255),
            Forms\Components\TextInput::make('tipo_estrutura')->label('Tipo de Estrutura (ex.: Alvenaria)')->maxLength(255),
            Forms\Components\Select::make('padrao')->label('Padrão')
                ->options(['baixo' => 'Baixo', 'normal' => 'Normal', 'alto' => 'Alto']),
            Forms\Components\TextInput::make('valor_m2')->label('Valor m² (CUB)')->numeric()->prefix('R$')->required(),
            Forms\Components\TextInput::make('coeficiente')->label('Coeficiente Adotado')->numeric()->default(1),
            Forms\Components\TextInput::make('mes_referencia')->label('Mês de Referência (ex.: 2026-06)')->maxLength(20),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('tipologia')->label('Tipologia')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tipo_estrutura')->label('Estrutura')->placeholder('—'),
                Tables\Columns\TextColumn::make('padrao')->label('Padrão')->badge()->placeholder('—'),
                Tables\Columns\TextColumn::make('valor_m2')->label('Valor m²')->money('BRL'),
                Tables\Columns\TextColumn::make('coeficiente')->label('Coef.')->numeric(4),
                Tables\Columns\TextColumn::make('mes_referencia')->label('Referência')->placeholder('—'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPgvCubs::route('/')];
    }
}
