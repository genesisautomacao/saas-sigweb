<?php

namespace App\Filament\Resources\CadastroSocialResource\Pages;

use App\Filament\Resources\CadastroSocialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCadastroSocials extends ListRecords
{
    protected static string $resource = CadastroSocialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\CadastroSocialExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $cadastros = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($cadastros);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\CadastroSocialExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $cadastros = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($cadastros);
                    }),

                Actions\Action::make('export_csv')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-document')
                    ->action(fn($livewire, \App\Services\Exports\CadastroSocialExportService $svc) =>
                        $svc->exportToCsv($livewire->getFilteredTableQuery()->get())),

                Actions\Action::make('export_xml')
                    ->label('Exportar XML')
                    ->icon('heroicon-o-code-bracket')
                    ->action(fn($livewire, \App\Services\Exports\CadastroSocialExportService $svc) =>
                        $svc->exportToXml($livewire->getFilteredTableQuery()->get())),
            ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray'),

            Actions\CreateAction::make()
                ->label('Novo Cadastro Social')
                ->icon('heroicon-o-plus'),
        ];
    }
}