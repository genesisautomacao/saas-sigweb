<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZoneamentoRegraResource\Pages;
use App\Models\ZoneamentoRegra;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class ZoneamentoRegraResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = ZoneamentoRegra::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Consultas de Viabilidade';
    protected static ?string $modelLabel = 'Regra de Zoneamento';
    protected static ?string $pluralModelLabel = 'Regras de Zoneamento';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Definição da Regra')->schema([
                Forms\Components\Select::make('zona_sigla')
                    ->label('Zona de Uso')
                    ->options(fn() => \App\Models\Zona::pluck('name', 'sigla')->mapWithKeys(fn($name, $sigla) => [$sigla => "$sigla - $name"]))
                    ->searchable()
                    ->required(),
                
                Forms\Components\TextInput::make('classificacao')
                    ->label('Classificação (Ex: H1, CS2)')
                    ->required()
                    ->maxLength(10),

                Forms\Components\Select::make('status')
                    ->label('Veredito (Status)')
                    ->options([
                        'permitido' => 'Permitido',
                        'permissivel' => 'Permissível (Requer Anuência)',
                        'proibido' => 'Proibido'
                    ])
                    ->required()
                    ->native(false),
            ])->columns(3)
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('zona_sigla')
                    ->label('Zona')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('classificacao')
                    ->label('Classificação')
                    ->sortable()
                    ->searchable()
                    ->badge(),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'permitido' => 'success',
                        'permissivel' => 'warning',
                        'proibido' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('zona_sigla', 'asc')
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
            'index' => Pages\ListZoneamentoRegras::route('/'),
            'create' => Pages\CreateZoneamentoRegra::route('/create'),
            'edit' => Pages\EditZoneamentoRegra::route('/{record}/edit'),
        ];
    }
}