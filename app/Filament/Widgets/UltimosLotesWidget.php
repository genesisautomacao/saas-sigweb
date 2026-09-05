<?php

namespace App\Filament\Widgets;

use App\Models\Lote;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UltimosLotesWidget extends BaseWidget
{
    protected static ?string $heading = 'Últimas Atualizações de Lotes';

    /** Widget do módulo imobiliário (docs/Modulos_Permissoes.txt) */
    public static function canView(): bool
    {
        return \App\Support\Modulos::ativo('imobiliario');
    }

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

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
