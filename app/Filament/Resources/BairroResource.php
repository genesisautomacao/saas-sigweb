<?php
namespace App\Filament\Resources;
use App\Models\Bairro;
use App\Filament\Resources\BairroResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class BairroResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = Bairro::class;
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Módulo Imobiliário';
     protected static ?int $navigationSort = 1;
    
    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('setor')->label('Setor')->searchable(),
            ])
            ->actions([])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBairros::route('/')];
    }
}