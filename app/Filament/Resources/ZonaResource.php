<?php
namespace App\Filament\Resources;
use App\Models\Zona;
use App\Filament\Resources\ZonaResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class ZonaResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'imobiliario';
    protected static ?string $model = Zona::class;
    protected static ?string $navigationIcon = 'heroicon-o-globe-americas';
    protected static ?string $navigationGroup = 'Módulo Imobiliário';
     protected static ?int $navigationSort = 0;
    
    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('sigla')->label('Sigla')->badge()->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Nome da Zona')->sortable()->searchable()->weight('bold'),
                
                Tables\Columns\TextColumn::make('rgb')
                    ->label('Cor no Mapa')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return '-';
                        
                        // Se no banco estiver "255,0,0", ele adiciona o "rgb()". Se já tiver "#" ou "rgb", ele mantém.
                        $corCSS = (str_contains($state, 'rgb') || str_contains($state, '#')) ? $state : "rgb{$state}";
                        
                        return new \Illuminate\Support\HtmlString("
                            <div class='flex items-center gap-2'>
                                <div style='background-color: {$corCSS};' class='w-6 h-6 rounded-full border border-gray-300 shadow-sm'></div>
                                <span class='text-xs text-gray-500 font-mono'>{$state}</span>
                            </div>
                        ");
                    })
                    ->html(),
            ])
            ->actions([])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListZonas::route('/')];
    }
}