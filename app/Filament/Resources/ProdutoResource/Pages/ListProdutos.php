<?php

namespace App\Filament\Resources\ProdutoResource\Pages;

use App\Filament\Resources\ProdutoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProdutos extends ListRecords
{
    protected static string $resource = ProdutoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\ProdutoExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando Excel')->info()->send();

                        return $exportService->exportToExcel($livewire->getFilteredTableQuery()->get());
                    }),
                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\ProdutoExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando PDF')->info()->send();

                        return $exportService->exportToPdf($livewire->getFilteredTableQuery()->get());
                    }),
                Actions\Action::make('export_csv')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-document')
                    ->action(fn ($livewire, \App\Services\Exports\ProdutoExportService $s) => $s->exportToCsv($livewire->getFilteredTableQuery()->get())),
                Actions\Action::make('export_xml')
                    ->label('Exportar XML')
                    ->icon('heroicon-o-code-bracket')
                    ->action(fn ($livewire, \App\Services\Exports\ProdutoExportService $s) => $s->exportToXml($livewire->getFilteredTableQuery()->get())),
            ])->label('Exportar')->icon('heroicon-m-arrow-down-tray')->button()->color('gray'),

            Actions\CreateAction::make(),
        ];
    }
}
