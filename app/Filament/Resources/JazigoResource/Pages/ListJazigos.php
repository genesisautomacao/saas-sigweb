<?php

namespace App\Filament\Resources\JazigoResource\Pages;

use App\Filament\Resources\JazigoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;

class ListJazigos extends ListRecords
{
    protected static string $resource = JazigoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\JazigoExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $jazigos = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($jazigos);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\JazigoExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $jazigos = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($jazigos);
                    }),
            ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray'),

            // BOTÃO HÍBRIDO (POLÍGONO)
            Actions\Action::make('novo_jazigo')
                ->label('Novo Jazigo')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Forma de Cadastro Geográfico')
                ->modalDescription('Como deseja demarcar o polígono deste Jazigo?')
                ->modalSubmitActionLabel('Continuar')
                ->modalWidth('md')
                ->form([
                    Forms\Components\Radio::make('metodo')
                        ->hiddenLabel()
                        ->options([
                            'mapa' => '🗺️ Desenhar o Polígono no Mapa Interativo',
                            'geojson' => '💻 Importar Topografia (Colar GeoJSON)',
                        ])
                        ->default('mapa')
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($data['metodo'] === 'mapa') {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        $mapUrl = url('/app/' . $tenant->slug . '/mapa-interativo?layer=jazigos&action=create');
                        return redirect()->to($mapUrl);
                    } else {
                        return redirect()->to(JazigoResource::getUrl('create'));
                    }
                }),
        ];
    }
}