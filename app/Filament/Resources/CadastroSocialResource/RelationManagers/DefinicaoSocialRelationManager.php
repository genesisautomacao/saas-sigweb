<?php

namespace App\Filament\Resources\CadastroSocialResource\RelationManagers;

use App\Models\InformacaoSocial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DefinicaoSocialRelationManager extends RelationManager
{
    protected static string $relationship = 'informacoes';
    protected static ?string $title = 'Definição Social';
    protected static ?string $modelLabel = 'Informação Social';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('informacao_social_id')
                ->label('Informação Social')
                ->options(fn() => InformacaoSocial::pluck('name', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('valor')
                ->label('Valor / Observação')
                ->helperText('Ex.: "Sim", "Grave", "Nível 2"...')
                ->maxLength(255),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Informação Social')->sortable(),
                Tables\Columns\TextColumn::make('pivot.valor')->label('Valor')->default('—'),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Vincular Informação')
                    ->preloadRecordSelect()
                    ->form(fn(Tables\Actions\AttachAction $action) => [
                        $action->getRecordSelect(),
                        // tenant_id é obrigatório na pivot familia_informacoes
                        Forms\Components\Hidden::make('tenant_id')
                            ->default(fn() => \Filament\Facades\Filament::getTenant()?->id),
                        Forms\Components\TextInput::make('valor')->label('Valor / Observação')->maxLength(255),
                    ]),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ]);
    }
}
