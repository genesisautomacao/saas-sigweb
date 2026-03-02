<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeadPotentialResource\Pages;
use App\Filament\Resources\LeadPotentialResource\RelationManagers;
use App\Models\LeadPotential;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LeadPotentialResource extends Resource
{

    use \App\Traits\HasTenantModule;

    // 2. Define qual é o módulo que esta tela exige
    protected static ?string $tenantModule = 'leads';
    
    protected static ?string $model = LeadPotential::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationGroup = 'Configurações do CRM';
    protected static ?string $modelLabel = 'Potencial de Lead';
    protected static ?string $pluralModelLabel = 'Potenciais de Leads';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalhes do Potencial')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome (Ex: Premium / Acima 350 Entregas)')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Cor de Identificação')
                            ->nullable(),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Potencial Padrão?')
                            ->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn($record) => $record->color),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Padrão')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListLeadPotentials::route('/'),
            'create' => Pages\CreateLeadPotential::route('/create'),
            'edit' => Pages\EditLeadPotential::route('/{record}/edit'),
        ];
    }
}
