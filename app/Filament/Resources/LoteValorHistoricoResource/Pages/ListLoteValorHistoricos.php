<?php

namespace App\Filament\Resources\LoteValorHistoricoResource\Pages;

use App\Filament\Resources\LoteValorHistoricoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoteValorHistoricos extends ListRecords
{
    protected static string $resource = LoteValorHistoricoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\PgvExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $valores = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportValoresToExcel($valores);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\PgvExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $valores = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportValoresToPdf($valores);
                    }),
            ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray'),

            // Botão padrão de criação manual
            Actions\CreateAction::make()
                ->label('Novo Lançamento Manual'),
        ];
    }
}