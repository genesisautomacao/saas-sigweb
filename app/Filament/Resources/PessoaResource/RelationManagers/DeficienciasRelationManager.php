<?php

namespace App\Filament\Resources\PessoaResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DeficienciasRelationManager extends RelationManager
{
    protected static string $relationship = 'deficiencias';
    protected static ?string $title = 'Deficiências (CID)';
    protected static ?string $modelLabel = 'Deficiência';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tipo')->label('Tipo de Deficiência')
                ->options([
                    'fisica' => 'Física', 'visual' => 'Visual', 'auditiva' => 'Auditiva',
                    'intelectual' => 'Intelectual', 'mental' => 'Mental', 'multipla' => 'Múltipla',
                ])->required(),
            Forms\Components\TextInput::make('cid')->label('Número do CID')->maxLength(255),
            Forms\Components\Textarea::make('descricao')->label('Descrição')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge(),
                Tables\Columns\TextColumn::make('cid')->label('CID')->default('—'),
                Tables\Columns\TextColumn::make('descricao')->label('Descrição')->limit(50),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('Adicionar Deficiência')])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
