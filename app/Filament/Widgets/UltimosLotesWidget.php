<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Lote;

class UltimosLotesWidget extends BaseWidget
{
    protected static ?string $heading = 'Últimas Atualizações de Lotes';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Lote::where('tenant_id', \Filament\Facades\Filament::getTenant()->id)
                    ->latest('updated_at')
                    ->limit(7)
            )
            ->columns([
                Tables\Columns\TextColumn::make('numero_lote')
                    ->label('Número do Lote')
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('quadra.name')
                    ->label('Quadra')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Alteração')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->paginated(false); // Remove a paginação para ficar clean como widget
    }
}