<?php

namespace App\Filament\Resources\Concerns;

use App\Services\Exports\MobilidadeExportService;
use Filament\Actions;
use Filament\Notifications\Notification;

/**
 * ActionGroup "Exportar" (XLS/PDF/CSV/XML) das List pages da Mobilidade
 * Urbana — respeita os filtros/busca ativos da tabela (getFilteredTableQuery).
 */
trait TemExportacaoMobilidade
{
    /** @param  array  $with  relações a pré-carregar (evita N+1 nos exports) */
    protected function mobExportActionGroup(string $entidade, array $with = []): Actions\ActionGroup
    {
        $consulta = fn ($livewire) => $livewire->getFilteredTableQuery()->with($with)->get();

        $acao = function (string $formato, string $rotulo, string $icone) use ($entidade, $consulta) {
            return Actions\Action::make('export_'.$formato)
                ->label($rotulo)
                ->icon($icone)
                ->action(function ($livewire) use ($formato, $entidade, $consulta) {
                    Notification::make()->title('Gerando exportação...')->info()->send();
                    $service = app(MobilidadeExportService::class);
                    $metodo = 'exportTo'.ucfirst($formato);

                    return $service->{$metodo}($entidade, $consulta($livewire));
                });
        };

        return Actions\ActionGroup::make([
            $acao('excel', 'Exportar Excel', 'heroicon-o-table-cells'),
            $acao('pdf', 'Exportar PDF', 'heroicon-o-document-text'),
            $acao('csv', 'Exportar CSV', 'heroicon-o-document'),
            $acao('xml', 'Exportar XML', 'heroicon-o-code-bracket'),
        ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray');
    }
}
