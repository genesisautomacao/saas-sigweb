<?php

namespace App\Filament\Resources\LoteEstoqueResource\Pages;

use App\Filament\Resources\LoteEstoqueResource;
use App\Services\Exports\LoteEstoqueExportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoteEstoques extends ListRecords
{
    protected static string $resource = LoteEstoqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')->icon('heroicon-o-table-cells')
                    ->action(fn ($livewire, LoteEstoqueExportService $svc) =>
                        $svc->exportToExcel($livewire->getFilteredTableQuery()->with(['produto', 'fornecedor'])->get())),
                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')->icon('heroicon-o-document-text')
                    ->action(fn ($livewire, LoteEstoqueExportService $svc) =>
                        $svc->exportToPdf($livewire->getFilteredTableQuery()->with(['produto', 'fornecedor'])->get())),
                Actions\Action::make('export_xml')
                    ->label('Exportar XML')->icon('heroicon-o-code-bracket')
                    ->action(fn ($livewire, LoteEstoqueExportService $svc) =>
                        $svc->exportToXml($livewire->getFilteredTableQuery()->with(['produto', 'fornecedor'])->get())),
            ])->label('Relatório de Garantia')->icon('heroicon-m-arrow-down-tray')->button()->color('gray'),

            Actions\CreateAction::make(),
        ];
    }
}
