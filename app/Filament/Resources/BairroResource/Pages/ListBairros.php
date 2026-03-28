<?php

namespace App\Filament\Resources\BairroResource\Pages;

use App\Filament\Resources\BairroResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Services\Exports\BairroExportService;
use Filament\Forms;

class ListBairros extends ListRecords
{
    protected static string $resource = BairroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn(BairroExportService $service, $livewire) => $service->exportToExcel($livewire->getFilteredTableQuery()->get())),
                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(fn(BairroExportService $service, $livewire) => $service->exportToPdf($livewire->getFilteredTableQuery()->get())),
            ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray'),

            Actions\Action::make('novo_bairro')
                ->label('Novo Bairro')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Forma de Cadastro Geográfico')
                ->modalDescription('Como deseja registrar as delimitações deste Bairro?')
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
                        return redirect()->to(url('/app/' . $tenant->slug . '/mapa-interativo?layer=bairros&action=create'));
                    } else {
                        return redirect()->to(BairroResource::getUrl('create'));
                    }
                }),
        ];
    }
}