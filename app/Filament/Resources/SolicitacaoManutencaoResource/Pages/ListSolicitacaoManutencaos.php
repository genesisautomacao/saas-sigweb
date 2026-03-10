<?php

namespace App\Filament\Resources\SolicitacaoManutencaoResource\Pages;

use App\Filament\Resources\SolicitacaoManutencaoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSolicitacaoManutencaos extends ListRecords
{
    protected static string $resource = SolicitacaoManutencaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\SolicitacaoManutencaoExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $solicitacoes = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($solicitacoes);
                    }),
                    
                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\SolicitacaoManutencaoExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $solicitacoes = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($solicitacoes);
                    }),
            ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray'),

            Actions\CreateAction::make(),
        ];
    }
}