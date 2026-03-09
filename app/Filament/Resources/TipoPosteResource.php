<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoPosteResource\Pages;
use App\Models\TipoPoste;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class TipoPosteResource extends Resource
{
    use HasTenantModule;
    
    // Módulo necessário para acessar (precisaremos adicionar no Tenant depois)
    protected static ?string $tenantModule = 'iluminacao'; 

    protected static ?string $model = TipoPoste::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-vertical';
    protected static ?string $navigationGroup = 'Iluminação Pública';
    protected static ?string $modelLabel = 'Tipo de Poste';
    protected static ?string $pluralModelLabel = 'Tipos de Poste';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalhes do Tipo')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome / Descrição (Ex: Poste de Concreto Duplo)')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome / Descrição')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->filters([])
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTipoPostes::route('/'),
        ];
    }
}