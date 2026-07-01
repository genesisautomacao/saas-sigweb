<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmpreendimentoResource\Pages;
use App\Models\Empreendimento;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmpreendimentoResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'social';
    protected static ?string $tenantRelationshipName = 'empreendimentos';

    protected static ?string $model = Empreendimento::class;
    protected static ?string $navigationIcon = 'heroicon-o-home-modern';
    protected static ?string $navigationGroup = 'Módulo Social';
    protected static ?string $modelLabel = 'Empreendimento';
    protected static ?string $pluralModelLabel = 'Empreendimentos';
    protected static ?int $navigationSort = 27;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome (ex.: Residencial Bem Viver)')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\TextInput::make('num_unidades')->label('Nº de Unidades')->numeric(),
            Forms\Components\TextInput::make('endereco')->label('Endereço')->maxLength(255),
            Forms\Components\Textarea::make('descricao')->label('Descrição')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Empreendimento')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('num_unidades')->label('Unidades')->badge()->default('—'),
                Tables\Columns\TextColumn::make('endereco')->label('Endereço')->limit(40)->default('—'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListEmpreendimentos::route('/')];
    }
}
