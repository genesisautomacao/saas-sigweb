<?php

namespace App\Filament\Resources\FaceQuadraResource\Pages;

use App\Filament\Resources\FaceQuadraResource;
use App\Services\Pgv\PgvFaceExportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFaceQuadras extends ListRecords
{
    protected static string $resource = FaceQuadraResource::class;

    protected function getHeaderActions(): array
    {
        $with = ['quadra', 'logradouro', 'zona'];

        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')->icon('heroicon-o-table-cells')
                    ->action(fn($livewire, PgvFaceExportService $svc) =>
                        $svc->exportToExcel($livewire->getFilteredTableQuery()->with($with)->get())),
                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')->icon('heroicon-o-document-text')
                    ->action(fn($livewire, PgvFaceExportService $svc) =>
                        $svc->exportToPdf($livewire->getFilteredTableQuery()->with($with)->get())),
                Actions\Action::make('export_xml')
                    ->label('Exportar XML')->icon('heroicon-o-code-bracket')
                    ->action(fn($livewire, PgvFaceExportService $svc) =>
                        $svc->exportToXml($livewire->getFilteredTableQuery()->with($with)->get())),
            ])->label('Relatório de Faces')->icon('heroicon-m-arrow-down-tray')->button()->color('gray'),

            Actions\CreateAction::make(),
        ];
    }
}
