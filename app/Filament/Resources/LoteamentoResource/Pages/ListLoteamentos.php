<?php
namespace App\Filament\Resources\LoteamentoResource\Pages;
use App\Filament\Resources\LoteamentoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Services\Exports\LoteamentoExportService;

class ListLoteamentos extends ListRecords
{
    protected static string $resource = LoteamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')->label('Exportar Excel')->icon('heroicon-o-table-cells')
                    ->action(fn(LoteamentoExportService $service) => $service->exportToExcel($this->getFilteredTableQuery()->get())),
                Actions\Action::make('export_pdf')->label('Exportar PDF')->icon('heroicon-o-document-text')
                    ->action(fn(LoteamentoExportService $service) => $service->exportToPdf($this->getFilteredTableQuery()->get())),
            ])->label('Exportar')->icon('heroicon-m-arrow-down-tray')->button()->color('gray'),
        ];
    }
}