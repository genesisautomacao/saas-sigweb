<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeadStatusResource\Pages;
use App\Models\LeadStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LeadStatusResource extends Resource
{
    use \App\Traits\HasTenantModule;

    protected static ?string $tenantModule = 'leads';

    protected static ?string $model = LeadStatus::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Configurações do CRM';
    protected static ?string $modelLabel = 'Status de Lead';
    protected static ?string $pluralModelLabel = 'Status de Leads';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalhes do Status')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Status')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Cor de Identificação')
                            ->nullable(),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Status Padrão?')
                            ->helperText('Se marcado, novos leads receberão este status automaticamente.')
                            ->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // 1. Abre a tabela já ordenada pela coluna 'order'
            ->defaultSort('order', 'asc')
            // 2. Habilita o recurso de arrastar e soltar para reordenar
            ->reorderable('order')
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('#')
                    ->sortable()
                    ->badge()
                    ->color('gray'), // Dá um visual de "etiqueta" para o número

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->badge()
                    ->color(fn($record) => $record->color),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Padrão')
                    ->boolean(),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeadStatuses::route('/'),
            'create' => Pages\CreateLeadStatus::route('/create'),
            'edit' => Pages\EditLeadStatus::route('/{record}/edit'),
        ];
    }
}