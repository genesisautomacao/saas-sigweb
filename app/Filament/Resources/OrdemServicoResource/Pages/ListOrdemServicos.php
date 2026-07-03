<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages;

use App\Filament\Resources\OrdemServicoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrdemServicos extends ListRecords
{
    protected static string $resource = OrdemServicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn ($livewire, \App\Services\Exports\OrdemServicoExportService $exportService) => $exportService->exportToExcel($livewire->getFilteredTableQuery()->get())),
                    
                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(fn ($livewire, \App\Services\Exports\OrdemServicoExportService $exportService) => $exportService->exportToPdf($livewire->getFilteredTableQuery()->get())),

                Actions\Action::make('export_csv')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-document')
                    ->action(fn ($livewire, \App\Services\Exports\OrdemServicoExportService $s) => $s->exportToCsv($livewire->getFilteredTableQuery()->get())),

                Actions\Action::make('export_xml')
                    ->label('Exportar XML')
                    ->icon('heroicon-o-code-bracket')
                    ->action(fn ($livewire, \App\Services\Exports\OrdemServicoExportService $s) => $s->exportToXml($livewire->getFilteredTableQuery()->get())),
            ])->label('Exportar')->icon('heroicon-m-arrow-down-tray')->button()->color('gray'),

            Actions\CreateAction::make(),
        ];
    }
}