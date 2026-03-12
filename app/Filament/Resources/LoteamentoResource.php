<?php
namespace App\Filament\Resources;
use App\Models\Loteamento;
use App\Filament\Resources\LoteamentoResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class LoteamentoResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = Loteamento::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Módulo Imobiliário';
    
    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Nome do Loteamento')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('setor')->label('Setor')->searchable(),
            ])
            ->actions([])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListLoteamentos::route('/')];
    }
}