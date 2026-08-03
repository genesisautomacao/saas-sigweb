<?php

namespace App\Filament\Resources\LoteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EdificacoesRelationManager extends RelationManager
{
    protected static string $relationship = 'edificacoes';

    protected static ?string $title = 'Edificações (Construções)';

    protected static ?string $icon = 'heroicon-o-home-modern';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('area_geo')
                    ->label('Área (m²)')
                    ->numeric()
                    ->required(),

                // Refatoração PoC Tangará: TODOS os atributos descritivos da edificação
                // são campos customizados do município (o kit inicial cria o conjunto
                // padrão — tipo_edificacao, pavimento, tp_construcao, estado_conservacao).
                Forms\Components\Section::make('Dados da Edificação')
                    ->visible(fn () => \App\Services\Coleta\CampoCustomizadoService::definicoes('edificacao')->isNotEmpty())
                    ->schema(fn () => \App\Services\Coleta\CampoCustomizadoService::componentes('edificacao'))
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID'),
                // Atributos descritivos vivem em dados_customizados (JSONB)
                Tables\Columns\TextColumn::make('dados_customizados.tipo_edificacao')
                    ->label('Tipo de Edificação')->badge()->color('info')->default('—'),
                Tables\Columns\TextColumn::make('dados_customizados.tp_construcao')
                    ->label('Construção')->badge()->color('gray')->default('—'),
                Tables\Columns\TextColumn::make('dados_customizados.estado_conservacao')
                    ->label('Conservação')->badge()->default('—')
                    ->color(fn ($state) => match ($state) {
                        'Bom', 'Ótimo', 'Nova/Ótima' => 'success',
                        'Médio', 'Regular' => 'warning',
                        'Ruim', 'Mau' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('dados_customizados.pavimento')
                    ->label('Pavimentos')->alignCenter()->default('—'),
                Tables\Columns\TextColumn::make('area_geo')
                    ->label('Área Construída')
                    ->suffix(' m²')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Nova Edificação'),
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
