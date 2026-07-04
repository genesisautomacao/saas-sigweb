<?php

namespace App\Filament\Resources\ChamadoResource\Pages;

use App\Filament\Resources\ChamadoResource;
use App\Services\Exports\ChamadoExportService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListChamados extends ListRecords
{
    protected static string $resource = ChamadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, ChamadoExportService $exportService) {
                        Notification::make()->title('Exportando para Excel')->info()->send();

                        return $exportService->exportToExcel(self::consultaExport($livewire));
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, ChamadoExportService $exportService) {
                        Notification::make()->title('Exportando para PDF')->info()->send();

                        return $exportService->exportToPdf(self::consultaExport($livewire));
                    }),

                Actions\Action::make('export_csv')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-document')
                    ->action(function ($livewire, ChamadoExportService $exportService) {
                        Notification::make()->title('Exportando para CSV')->info()->send();

                        return $exportService->exportToCsv(self::consultaExport($livewire));
                    }),

                Actions\Action::make('export_xml')
                    ->label('Exportar XML')
                    ->icon('heroicon-o-code-bracket')
                    ->action(function ($livewire, ChamadoExportService $exportService) {
                        Notification::make()->title('Exportando para XML')->info()->send();

                        return $exportService->exportToXml(self::consultaExport($livewire));
                    }),
            ])
                ->label('Exportar')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('gray'),

            Actions\CreateAction::make()->label('Novo Chamado'),
        ];
    }

    /** Respeita os filtros/busca ativos da tabela e já carrega as relações do relatório (evita N+1). */
    private static function consultaExport($livewire)
    {
        return $livewire->getFilteredTableQuery()
            ->with(['categoria', 'fluxo', 'faseAtual'])
            ->get();
    }
}
