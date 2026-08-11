<?php

namespace App\Filament\Resources\PessoaResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * TR Tangará Intranet #12 (correção de 2026-08-06): imóveis onde a pessoa é a
 * proprietária (unidades imobiliárias via proprietario_id). Somente leitura —
 * o vínculo é gerido pela ficha da unidade/sincronização tributária.
 */
class ImoveisRelationManager extends RelationManager
{
    protected static string $relationship = 'unidadesImobiliarias';

    protected static ?string $title = 'Imóveis';

    protected static ?string $modelLabel = 'Imóvel';

    protected static ?string $pluralModelLabel = 'Imóveis';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('inscricao_imobiliaria')
            ->columns([
                Tables\Columns\TextColumn::make('inscricao_imobiliaria')
                    ->label('Inscrição Imobiliária')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('codigo_imovel_tributario')
                    ->label('Código Tributário')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('logradouro_nome')
                    ->label('Endereço')
                    ->formatStateUsing(fn ($state, $record) => trim(
                        ($state ?: '—').($record->numero_imovel ? ', '.$record->numero_imovel : '')
                    ))
                    ->searchable(),

                Tables\Columns\TextColumn::make('lote.numero_lote')
                    ->label('Lote')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('lote.quadra.name')
                    ->label('Quadra')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('nome_edificio')
                    ->label('Edifício')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                // Somente leitura: o vínculo de propriedade nasce na unidade
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('Nenhum imóvel vinculado')
            ->emptyStateDescription('Esta pessoa não consta como proprietária de nenhuma unidade imobiliária.');
    }
}
