<?php

namespace App\Filament\Resources\PessoaResource\RelationManagers;

use App\Models\TipoRenda;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RendasRelationManager extends RelationManager
{
    protected static string $relationship = 'rendas';
    protected static ?string $title = 'Rendas';
    protected static ?string $modelLabel = 'Renda';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tipo_renda_id')->label('Tipo de Renda')
                ->options(fn() => TipoRenda::pluck('name', 'id'))->searchable()->required(),
            Forms\Components\TextInput::make('valor')->label('Valor (R$)')->numeric()->prefix('R$')->required(),
            Forms\Components\Toggle::make('compoe_renda_familiar')
                ->label('Compõe renda familiar')
                ->default(true)
                ->helperText('Se ligado, entra no cálculo da renda familiar da família (item 097).'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tipoRenda.name')->label('Tipo')->default('—'),
                Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL'),
                Tables\Columns\IconColumn::make('compoe_renda_familiar')->label('Compõe renda')->boolean(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('Adicionar Renda')])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
