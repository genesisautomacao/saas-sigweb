<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CnaeResource\Pages;
use App\Models\Cnae;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class CnaeResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = Cnae::class;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationGroup = 'Consultas de Viabilidade';
    protected static ?string $modelLabel = 'CNAE e Atividade';
    protected static ?string $pluralModelLabel = 'CNAEs e Atividades';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados da Atividade')->schema([
                Forms\Components\TextInput::make('codigo')
                    ->label('Código CNAE')
                    ->required()
                    ->maxLength(20)
                    ->placeholder('Ex: 01.21-1'),
                
                Forms\Components\TextInput::make('descricao')
                    ->label('Descrição da Atividade')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\TagsInput::make('classificacoes')
                    ->label('Classificações Urbanísticas (Ex: CS1, I2, H1)')
                    ->helperText('Pressione ENTER para adicionar cada classificação.')
                    ->separator(',')
                    ->columnSpanFull(),
            ])->columns(2)
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('CNAE')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('descricao')
                    ->label('Descrição')
                    ->searchable()
                    ->wrap(),
                
                Tables\Columns\TagsColumn::make('classificacoes')
                    ->label('Classificações')
                    ->searchable(),
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
            'index' => Pages\ListCnaes::route('/'),
            'create' => Pages\CreateCnae::route('/create'),
            'edit' => Pages\EditCnae::route('/{record}/edit'),
        ];
    }
}