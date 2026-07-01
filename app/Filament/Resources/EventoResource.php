<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventoResource\Pages;
use App\Models\Evento;
use App\Models\Programa;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventoResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'social';
    protected static ?string $tenantRelationshipName = 'eventos';

    protected static ?string $model = Evento::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Módulo Social';
    protected static ?string $modelLabel = 'Evento';
    protected static ?string $pluralModelLabel = 'Eventos';
    protected static ?int $navigationSort = 25;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome do Evento')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\DatePicker::make('data')->label('Data'),
            Forms\Components\Select::make('programa_id')->label('Programa Vinculado')
                ->options(fn() => Programa::pluck('name', 'id'))->searchable(),
            Forms\Components\TextInput::make('local')->label('Local')->maxLength(255)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Evento')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('data')->label('Data')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('programa.name')->label('Programa')->default('—'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListEventos::route('/')];
    }
}
