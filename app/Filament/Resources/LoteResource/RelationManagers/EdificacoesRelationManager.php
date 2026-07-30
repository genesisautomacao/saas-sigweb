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
                // R67-2 — rótulo/valores definidos pelo município
                \App\Services\Coleta\CampoDominioService::aplicar(Forms\Components\Select::make('tipo')->required(), 'edificacao', 'tipo'),
                \App\Services\Coleta\CampoDominioService::aplicar(Forms\Components\Select::make('tp_construcao')->required(), 'edificacao', 'tp_construcao'),
                \App\Services\Coleta\CampoDominioService::aplicar(
                    Forms\Components\TextInput::make('caracteristica_construcao')
                        ->placeholder('Ex: Pavimento 1, Anexo, Edícula...')
                        ->maxLength(255)
                        ->nullable(),
                    'edificacao', 'caracteristica_construcao'
                ),
                \App\Services\Coleta\CampoDominioService::aplicar(Forms\Components\Select::make('estado_conservacao')->required(), 'edificacao', 'estado_conservacao'),
                \App\Services\Coleta\CampoDominioService::aplicar(
                    Forms\Components\TextInput::make('pavimento')->numeric()->minValue(1)->maxValue(99)->nullable(),
                    'edificacao', 'pavimento'
                ),
                Forms\Components\TextInput::make('area_geo')
                    ->label('Área (m²)')
                    ->numeric()
                    ->required(),

                // R67-1 — campos criados pelo município
                Forms\Components\Section::make('Campos do Município')
                    ->visible(fn () => \App\Services\Coleta\CampoCustomizadoService::definicoes('edificacao')->isNotEmpty())
                    ->schema(fn () => \App\Services\Coleta\CampoCustomizadoService::componentes('edificacao'))
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID'),
                Tables\Columns\TextColumn::make('tipo')->label('Finalidade')->badge()->color('info'),
                Tables\Columns\TextColumn::make('tp_construcao')->label('Construção')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('estado_conservacao')->label('Conservação')->badge()
                    ->color(fn ($state) => match ($state) {
                        'Bom' => 'success',
                        'Médio' => 'warning',
                        'Regular' => 'warning',
                        'Ruim' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('pavimento')->label('Pavimentos')->alignCenter(),
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
