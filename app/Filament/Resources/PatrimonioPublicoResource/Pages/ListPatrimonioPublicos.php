<?php

namespace App\Filament\Resources\PatrimonioPublicoResource\Pages;

use App\Filament\Resources\PatrimonioPublicoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;

class ListPatrimonioPublicos extends ListRecords
{
    protected static string $resource = PatrimonioPublicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\PatrimonioPublicoExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $records = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($records);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\PatrimonioPublicoExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $records = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($records);
                    }),
            ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray'),

            Actions\Action::make('novo_patrimonio')
                ->label('Novo Patrimônio')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Forma de Cadastro Geográfico')
                ->modalDescription('Como deseja registrar este Patrimônio Público?')
                ->modalSubmitActionLabel('Continuar')
                ->modalWidth('md')
                ->form([
                    Forms\Components\Radio::make('metodo')
                        ->hiddenLabel()
                        ->options([
                            'mapa' => '🗺️ Desenhar / Marcar no Mapa Interativo',
                            'geojson' => '💻 Preencher Manualmente / Importar GeoJSON',
                        ])
                        ->default('mapa')
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($data['metodo'] === 'mapa') {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        $mapUrl = url('/app/' . $tenant->slug . '/mapa-interativo?layer=patrimonio_publicos&action=create');
                        return redirect()->to($mapUrl);
                    } else {
                        return redirect()->to(PatrimonioPublicoResource::getUrl('create'));
                    }
                }),
        ];
    }
}