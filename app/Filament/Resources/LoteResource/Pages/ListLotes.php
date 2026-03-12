<?php

namespace App\Filament\Resources\LoteResource\Pages;

use App\Filament\Resources\LoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;

class ListLotes extends ListRecords
{
    protected static string $resource = LoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\LoteExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $lotes = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($lotes);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\LoteExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $lotes = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($lotes);
                    }),
            ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray'),

            Actions\Action::make('novo_lote')
                ->label('Novo Lote')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Forma de Cadastro Geográfico')
                ->modalDescription('Como deseja demarcar o polígono deste Lote?')
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
                        $mapUrl = url('/app/' . $tenant->slug . '/mapa-interativo?layer=lotes&action=create');
                        return redirect()->to($mapUrl);
                    } else {
                        return redirect()->to(LoteResource::getUrl('create'));
                    }
                }),
        ];
    }
}