<?php

namespace App\Filament\Resources\LogradouroCemiterioResource\Pages;

use App\Filament\Resources\LogradouroCemiterioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;

class ListLogradouroCemiterios extends ListRecords
{
    protected static string $resource = LogradouroCemiterioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\LogradouroCemiterioExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $logradouros = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($logradouros);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\LogradouroCemiterioExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $logradouros = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($logradouros);
                    }),
            ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray'),

            // BOTÃO HÍBRIDO (LINHA)
            Actions\Action::make('nova_rua')
                ->label('Nova Rua/Viela')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Forma de Cadastro')
                ->modalDescription('Como deseja traçar a linha desta rua?')
                ->modalSubmitActionLabel('Continuar')
                ->modalWidth('md')
                ->form([
                    Forms\Components\Radio::make('metodo')
                        ->hiddenLabel()
                        ->options([
                            'mapa' => '🗺️ Traçar a Linha no Mapa Interativo',
                            'geojson' => '💻 Importar Topografia (Colar GeoJSON)',
                        ])
                        ->default('mapa')
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($data['metodo'] === 'mapa') {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        $mapUrl = url('/app/' . $tenant->slug . '/mapa-interativo?layer=logradouros_cemiterio&action=create');
                        return redirect()->to($mapUrl);
                    } else {
                        return redirect()->to(LogradouroCemiterioResource::getUrl('create'));
                    }
                }),
        ];
    }
}