<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\RoleExportService $exportService) {
                        // O $livewire aqui pega a tabela filtrada lá de baixo!
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $roles = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($roles);
                    }),
                    
                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\RoleExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $roles = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($roles);
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