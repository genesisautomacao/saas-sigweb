<?php

namespace App\Filament\Resources\QuadraResource\Pages;

use App\Filament\Resources\QuadraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Services\Exports\QuadraExportService;
use Filament\Forms;

class ListQuadras extends ListRecords
{
    protected static string $resource = QuadraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn(QuadraExportService $service, $livewire) => $service->exportToExcel($livewire->getFilteredTableQuery()->get())),
                
                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(fn(QuadraExportService $service, $livewire) => $service->exportToPdf($livewire->getFilteredTableQuery()->get())),

                Actions\Action::make('export_xml')
                    ->label('Exportar XML')
                    ->icon('heroicon-o-code-bracket')
                    ->action(fn(QuadraExportService $service, $livewire) => $service->exportToXml($livewire->getFilteredTableQuery()->get())),
            ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray'),

            Actions\Action::make('nova_quadra')
                ->label('Nova Quadra')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Forma de Cadastro Geográfico')
                ->modalDescription('Como deseja registrar as delimitações desta Quadra?')
                ->modalSubmitActionLabel('Continuar')
                ->modalWidth('md')
                ->form([
                    Forms\Components\Radio::make('metodo')
                        ->hiddenLabel()
                        ->options([
                            'mapa' => '🗺️ Desenhar o Polígono no Mapa Interativo',
                            'geojson' => '💻 Preencher Manualmente / Importar GeoJSON',
                        ])
                        ->default('mapa')
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($data['metodo'] === 'mapa') {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return redirect()->to(url('/app/' . $tenant->slug . '/mapa-interativo?layer=quadras&action=create'));
                    } else {
                        return redirect()->to(QuadraResource::getUrl('create'));
                    }
                }),
        ];
    }
}