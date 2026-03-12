<?php
namespace App\Filament\Resources\QuadraResource\Pages;
use App\Filament\Resources\QuadraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Services\Exports\QuadraExportService;

class ListQuadras extends ListRecords
{
    protected static string $resource = QuadraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')->label('Exportar Excel')->icon('heroicon-o-table-cells')
                    ->action(fn(QuadraExportService $service) => $service->exportToExcel($this->getFilteredTableQuery()->get())),
                Actions\Action::make('export_pdf')->label('Exportar PDF')->icon('heroicon-o-document-text')
                    ->action(fn(QuadraExportService $service) => $service->exportToPdf($this->getFilteredTableQuery()->get())),
            ])->label('Exportar')->icon('heroicon-m-arrow-down-tray')->button()->color('gray'),
        ];
    }
}