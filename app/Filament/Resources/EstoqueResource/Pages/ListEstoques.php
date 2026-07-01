<?php

namespace App\Filament\Resources\EstoqueResource\Pages;

use App\Filament\Resources\EstoqueResource;
use App\Services\Exports\EstoqueExportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEstoques extends ListRecords
{
    protected static string $resource = EstoqueResource::class;

    protected function getHeaderActions(): array
    {
        $with = ['produto.familia', 'localEstoque', 'tipoEstoque', 'loteEstoque'];

        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')->icon('heroicon-o-table-cells')
                    ->action(fn ($livewire, EstoqueExportService $svc) =>
                        $svc->exportToExcel($livewire->getFilteredTableQuery()->with($with)->get())),
                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')->icon('heroicon-o-document-text')
                    ->action(fn ($livewire, EstoqueExportService $svc) =>
                        $svc->exportToPdf($livewire->getFilteredTableQuery()->with($with)->get())),
                Actions\Action::make('export_xml')
                    ->label('Exportar XML')->icon('heroicon-o-code-bracket')
                    ->action(fn ($livewire, EstoqueExportService $svc) =>
                        $svc->exportToXml($livewire->getFilteredTableQuery()->with($with)->get())),
            ])->label('Relatório de Saldo')->icon('heroicon-m-arrow-down-tray')->button()->color('gray'),
        ];
    }
}
