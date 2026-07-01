<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServicoSocialResource\Pages;
use App\Models\Entidade;
use App\Models\ServicoSocial;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServicoSocialResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'social';
    protected static ?string $tenantRelationshipName = 'servicoSociais';
    protected static ?string $slug = 'servicos-sociais';

    protected static ?string $model = ServicoSocial::class;
    protected static ?string $navigationIcon = 'heroicon-o-hand-raised';
    protected static ?string $navigationGroup = 'Módulo Social';
    protected static ?string $modelLabel = 'Serviço Social';
    protected static ?string $pluralModelLabel = 'Serviços Sociais';
    protected static ?int $navigationSort = 23;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome do Serviço')->required()->maxLength(255),
            Forms\Components\Select::make('entidade_id')->label('Entidade Responsável')
                ->options(fn() => Entidade::pluck('name', 'id'))->searchable(),
            Forms\Components\Textarea::make('descricao')->label('Descrição')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Serviço')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('entidade.name')->label('Entidade')->default('—')->searchable(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListServicoSociais::route('/')];
    }
}
