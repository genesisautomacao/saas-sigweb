<?php

namespace App\Filament\Resources\CadastroSocialResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OcorrenciasRelationManager extends RelationManager
{
    protected static string $relationship = 'ocorrencias';
    protected static ?string $title = 'Ocorrências Sociais';
    protected static ?string $modelLabel = 'Ocorrência';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('data')->label('Data')->default(now()),
            Forms\Components\Select::make('tipo')->label('Tipo de Ocorrência')
                ->options([
                    'alteracao_cadastral' => 'Alteração Cadastral',
                    'atendimento' => 'Atendimento Social',
                    'encaminhamento' => 'Encaminhamento',
                    'visita' => 'Visita Domiciliar',
                    'denuncia' => 'Denúncia',
                    'outro' => 'Outro',
                ])->required(),
            Forms\Components\Textarea::make('descricao')->label('Descrição')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('data', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('data')->label('Data')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge(),
                Tables\Columns\TextColumn::make('descricao')->label('Descrição')->limit(60),
                Tables\Columns\TextColumn::make('user.name')->label('Registrado por')->default('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Registrar Ocorrência')
                    ->mutateFormDataUsing(fn(array $data) => array_merge($data, ['user_id' => auth()->id()])),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
