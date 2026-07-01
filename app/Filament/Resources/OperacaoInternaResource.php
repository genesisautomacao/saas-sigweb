<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OperacaoInternaResource\Pages;
use App\Models\OperacaoInterna;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OperacaoInternaResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'estoque';

    protected static ?string $model = OperacaoInterna::class;
    protected static ?string $tenantRelationshipName = 'operacaoInternas';
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Estoque e Almoxarifado';
    protected static ?string $modelLabel = 'Operação Interna';
    protected static ?string $pluralModelLabel = 'Operações Internas';
    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome (ex.: Nota de Entrada por Compra)')->required()->maxLength(255),
            Forms\Components\Select::make('sentido')
                ->label('Sentido no Estoque')
                ->options([
                    'entrada'        => 'Entrada (soma)',
                    'saida'          => 'Saída (subtrai)',
                    'transferencia'  => 'Transferência (entre locais/tipos)',
                ])
                ->required(),
            Forms\Components\Toggle::make('is_active')->label('Ativa')->default(true),
            Forms\Components\Textarea::make('description')->label('Descrição')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Operação')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sentido')->label('Sentido')->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'entrada' => 'Entrada', 'saida' => 'Saída', 'transferencia' => 'Transferência', default => $state,
                    })
                    ->color(fn($state) => match ($state) {
                        'entrada' => 'success', 'saida' => 'danger', 'transferencia' => 'info', default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')->label('Ativa')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOperacaoInternas::route('/')];
    }
}
