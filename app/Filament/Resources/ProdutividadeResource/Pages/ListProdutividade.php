<?php

namespace App\Filament\Resources\ProdutividadeResource\Pages;

use App\Filament\Resources\ProdutividadeResource;
use App\Services\Exports\ProdutividadeExportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProdutividade extends ListRecords
{
    protected static string $resource = ProdutividadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, ProdutividadeExportService $exportService) {
                        \Filament\Notifications\Notification::make()
                            ->title('Exportando para Excel')
                            ->info()
                            ->send();
                        $lotes = $livewire->getFilteredTableQuery()
                            ->with(['quadra', 'zona', 'coletor', 'unidadesImobiliarias', 'edificacoes'])
                            ->get();
                        return $exportService->exportToExcel($lotes);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF Detalhado')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, ProdutividadeExportService $exportService) {
                        \Filament\Notifications\Notification::make()
                            ->title('Gerando PDF detalhado…')
                            ->info()
                            ->send();
                        $lotes = $livewire->getFilteredTableQuery()
                            ->with(['quadra', 'zona', 'coletor', 'unidadesImobiliarias', 'edificacoes'])
                            ->get();
                        return $exportService->exportToPdf($lotes);
                    }),
            ])
                ->label('Exportar')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('gray'),
        ];
    }
}
