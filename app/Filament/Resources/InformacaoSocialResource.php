<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InformacaoSocialResource\Pages;
use App\Models\InformacaoSocial;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InformacaoSocialResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'social';
    protected static ?string $tenantRelationshipName = 'informacaoSociais';
    protected static ?string $slug = 'informacoes-sociais';

    protected static ?string $model = InformacaoSocial::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Módulo Social';
    protected static ?string $modelLabel = 'Informação Social';
    protected static ?string $pluralModelLabel = 'Informações Sociais';
    protected static ?int $navigationSort = 26;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Indicador (ex.: Insegurança Alimentar, Trabalho Infantil)')->required()->maxLength(255),
            Forms\Components\Textarea::make('descricao')->label('Descrição')->rows(3)->columnSpanFull(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Informação Social')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('descricao')->label('Descrição')->limit(60),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListInformacaoSociais::route('/')];
    }
}
