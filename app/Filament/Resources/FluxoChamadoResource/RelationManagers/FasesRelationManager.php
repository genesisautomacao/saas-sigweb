<?php

namespace App\Filament\Resources\FluxoChamadoResource\RelationManagers;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FasesRelationManager extends RelationManager
{
    protected static string $relationship = 'fases';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static ?string $title = 'Fases do Fluxo';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome da Fase (ex.: Aberto, Em análise, Resolvido)')->required()->maxLength(255),
            Forms\Components\ColorPicker::make('cor')->label('Cor')->default('#6b7280'),
            Forms\Components\TextInput::make('ordem')->label('Ordem')->numeric()->default(0), // item 153

            Forms\Components\TextInput::make('duracao_minutos')->label('Duração da fase (minutos)')->numeric(), // item 150
            Forms\Components\Toggle::make('aviso_duracao')->label('Emitir aviso de duração')->default(false), // item 150
            Forms\Components\Toggle::make('encerramento')->label('É a fase de encerramento (última)')->default(false), // item 152

            Forms\Components\Select::make('usuarios_autorizados') // item 151
                ->label('Usuários autorizados a ver esta fase')
                ->multiple()
                ->searchable()
                ->options(function () {
                    $tid = Filament::getTenant()->id;

                    return User::query()
                        ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tid))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->helperText('Deixe vazio = todos os usuários veem esta fase.'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                Tables\Columns\TextColumn::make('ordem')->label('#')->sortable(),
                Tables\Columns\ColorColumn::make('cor')->label('Cor'),
                Tables\Columns\TextColumn::make('nome')->label('Fase')->weight('bold'),
                Tables\Columns\TextColumn::make('duracao_minutos')->label('Duração (min)')->placeholder('—'),
                Tables\Columns\IconColumn::make('aviso_duracao')->label('Aviso')->boolean(),
                Tables\Columns\IconColumn::make('encerramento')->label('Encerra?')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => array_merge($data, ['tenant_id' => Filament::getTenant()->id])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
