<?php

namespace App\Filament\Resources\TipoPatrimonioResource\Pages;

use App\Filament\Resources\TipoPatrimonioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTipoPatrimonios extends ListRecords
{
    protected static string $resource = TipoPatrimonioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\TipoPatrimonioExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $records = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($records);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\TipoPatrimonioExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $records = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($records);
                    }),
            ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray'),

            Actions\CreateAction::make()
                ->label('Novo Tipo de Patrimônio')
                ->icon('heroicon-o-plus'),
        ];
    }
}