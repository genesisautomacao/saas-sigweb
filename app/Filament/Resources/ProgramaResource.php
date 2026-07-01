<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProgramaResource\Pages;
use App\Models\Programa;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProgramaResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'social';
    protected static ?string $tenantRelationshipName = 'programas';

    protected static ?string $model = Programa::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Módulo Social';
    protected static ?string $modelLabel = 'Programa';
    protected static ?string $pluralModelLabel = 'Programas';
    protected static ?int $navigationSort = 24;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome do Programa')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\DatePicker::make('data_inicio')->label('Início'),
            Forms\Components\DatePicker::make('data_fim')->label('Término'),
            Forms\Components\Textarea::make('descricao')->label('Descrição')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Programa')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('data_inicio')->label('Início')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('data_fim')->label('Término')->date('d/m/Y')->placeholder('—'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProgramas::route('/')];
    }
}
