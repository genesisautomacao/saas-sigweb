<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PgvDepreciacaoResource\Pages;
use App\Models\PgvDepreciacao;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PgvDepreciacaoResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'pgv';
    protected static ?string $tenantRelationshipName = 'pgvDepreciacoes';
    protected static ?string $slug = 'pgv-depreciacoes';

    protected static ?string $model = PgvDepreciacao::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-down';
    protected static ?string $navigationGroup = 'Gestão Tributária (PGV)';
    protected static ?string $modelLabel = 'Depreciação';
    protected static ?string $pluralModelLabel = 'Depreciação (Conservação × Idade)';
    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('estado_conservacao')->label('Estado de Conservação')
                ->options(['Bom' => 'Bom', 'Regular' => 'Regular', 'Ruim' => 'Ruim', 'Péssimo' => 'Péssimo'])
                ->required(),
            Forms\Components\TextInput::make('coeficiente')->label('Coeficiente (fator)')->numeric()->default(1)->required(),
            Forms\Components\TextInput::make('idade_de')->label('Idade De (anos)')->numeric()->default(0),
            Forms\Components\TextInput::make('idade_ate')->label('Idade Até (anos)')->numeric()->default(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('estado_conservacao')->label('Conservação')->badge()->searchable(),
                Tables\Columns\TextColumn::make('idade_de')->label('Idade De'),
                Tables\Columns\TextColumn::make('idade_ate')->label('Idade Até'),
                Tables\Columns\TextColumn::make('coeficiente')->label('Coeficiente')->numeric(4),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPgvDepreciacoes::route('/')];
    }
}
