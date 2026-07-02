<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PgvPoloResource\Pages;
use App\Models\PgvPolo;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PgvPoloResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'pgv';
    protected static ?string $tenantRelationshipName = 'pgvPolos';

    protected static ?string $model = PgvPolo::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Gestão Tributária (PGV)';
    protected static ?string $modelLabel = 'Pólo Valorizante';
    protected static ?string $pluralModelLabel = 'Pólos Valorizantes';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome (ex.: Praça Central)')->required()->maxLength(255),
            Forms\Components\Placeholder::make('geo_info')->label('Localização')
                ->content('O ponto do pólo é definido clicando no mapa (Ferramentas → Novo Pólo).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Pólo')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('geo_json')->label('No Mapa')
                    ->getStateUsing(fn(PgvPolo $r) => $r->geo_json !== null)->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPgvPolos::route('/')];
    }
}
