<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // O nosso botão Dropdown de Exportar vem PRIMEIRO, para ficar à esquerda
            Actions\ActionGroup::make([

                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\UserExportService $exportService) {
                        // O $livewire aqui pega a tabela filtrada lá de baixo!
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $users = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($users);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\UserExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $users = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($users);
                    }),

            ])
                ->label('Exportar')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('gray'),

            // O botão principal roxo de Criar fica à direita
            Actions\CreateAction::make(),
        ];
    }
}