<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoPatrimonioResource\Pages;
use App\Models\TipoPatrimonio;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class TipoPatrimonioResource extends Resource
{
    use HasTenantModule;
    
    protected static ?string $tenantModule = 'patrimonios';

    protected static ?string $model = TipoPatrimonio::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Patrimônios Públicos';
    protected static ?string $modelLabel = 'Tipo de Patrimônio';
    protected static ?string $pluralModelLabel = 'Tipos de Patrimônios';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Detalhes do Tipo')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome do Tipo (Ex: Praça, Estátua, Escola)')
                    ->required()
                    ->maxLength(255),
                
                Forms\Components\Textarea::make('description')
                    ->label('Descrição / Observações')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('Cód')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Nome do Tipo')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y')->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTipoPatrimonios::route('/'),
            'create' => Pages\CreateTipoPatrimonio::route('/create'),
            'edit' => Pages\EditTipoPatrimonio::route('/{record}/edit'),
        ];
    }
}