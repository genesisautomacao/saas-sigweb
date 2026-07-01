<?php

namespace App\Filament\Resources\EstoqueMovimentacaoResource\Pages;

use App\Filament\Resources\EstoqueMovimentacaoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEstoqueMovimentacaos extends ListRecords
{
    protected static string $resource = EstoqueMovimentacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\EstoqueMovimentacaoExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $movimentacoes = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($movimentacoes);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\EstoqueMovimentacaoExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $movimentacoes = $livewire->getFilteredTableQuery()->with(['itens.produto', 'itens.loteEstoque', 'origem', 'destino', 'operacaoInterna', 'tipoEstoqueOrigem', 'tipoEstoqueDestino', 'user'])->get();
                        return $exportService->exportToPdf($movimentacoes);
                    }),

                Actions\Action::make('export_xml')
                    ->label('Exportar XML')
                    ->icon('heroicon-o-code-bracket')
                    ->action(function ($livewire, \App\Services\Exports\EstoqueMovimentacaoExportService $exportService) {
                        $movimentacoes = $livewire->getFilteredTableQuery()->with(['itens.produto', 'itens.loteEstoque', 'origem', 'destino', 'operacaoInterna', 'tipoEstoqueOrigem', 'tipoEstoqueDestino', 'user'])->get();
                        return $exportService->exportToXml($movimentacoes);
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