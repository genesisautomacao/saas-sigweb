<?php

namespace App\Filament\Resources\PessoaResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContatosRelationManager extends RelationManager
{
    protected static string $relationship = 'contatos';
    protected static ?string $title = 'Contatos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Tipo de Contato')
                    ->options([
                        'celular' => 'Celular / WhatsApp',
                        'telefone' => 'Telefone Fixo',
                        'email' => 'E-mail',
                    ])
                    ->required()
                    ->live(),

                Forms\Components\TextInput::make('contact')
                    ->label('Contato')
                    ->required()
                    ->maxLength(255)
                    // Muda a máscara de acordo com o tipo selecionado
                    ->mask(fn (Forms\Get $get) => match ($get('type')) {
                        'celular' => '(99) 99999-9999',
                        'telefone' => '(99) 9999-9999',
                        default => null, // Email não tem máscara numérica
                    })
                    ->email(fn (Forms\Get $get) => $get('type') === 'email'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('contact')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'celular' => 'success',
                        'telefone' => 'info',
                        'email' => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('contact')
                    ->label('Contato')
                    ->copyable()
                    ->searchable(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Novo Contato'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}