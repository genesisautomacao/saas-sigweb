<?php

namespace App\Filament\Resources\ChamadoResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HistoricoFasesRelationManager extends RelationManager
{
    protected static string $relationship = 'historicoFases';

    protected static ?string $title = 'Histórico de Fases';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form->schema([]); // somente leitura (populado pela ação "Alterar Fase")
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('fase.nome')->label('Fase')->badge()->color('info')->placeholder('—'),
                Tables\Columns\TextColumn::make('usuario.name')->label('Alterado por')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([])
            ->actions([]);
    }
}
