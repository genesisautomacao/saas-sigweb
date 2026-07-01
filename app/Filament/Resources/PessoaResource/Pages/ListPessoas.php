<?php

namespace App\Filament\Resources\PessoaResource\Pages;

use App\Filament\Resources\PessoaResource;
use App\Services\Exports\PessoaSocialExportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPessoas extends ListRecords
{
    protected static string $resource = PessoaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')->icon('heroicon-o-table-cells')
                    ->action(fn($livewire, PessoaSocialExportService $svc) =>
                        $svc->exportToExcel($livewire->getFilteredTableQuery()->get())),
                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')->icon('heroicon-o-document-text')
                    ->action(fn($livewire, PessoaSocialExportService $svc) =>
                        $svc->exportToPdf($livewire->getFilteredTableQuery()->get())),
                Actions\Action::make('export_csv')
                    ->label('Exportar CSV')->icon('heroicon-o-document')
                    ->action(fn($livewire, PessoaSocialExportService $svc) =>
                        $svc->exportToCsv($livewire->getFilteredTableQuery()->get())),
                Actions\Action::make('export_xml')
                    ->label('Exportar XML')->icon('heroicon-o-code-bracket')
                    ->action(fn($livewire, PessoaSocialExportService $svc) =>
                        $svc->exportToXml($livewire->getFilteredTableQuery()->get())),
            ])->label('Exportar')->icon('heroicon-m-arrow-down-tray')->button()->color('gray'),

            Actions\CreateAction::make(),
        ];
    }
}
