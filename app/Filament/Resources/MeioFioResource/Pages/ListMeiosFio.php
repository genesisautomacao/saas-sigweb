<?php

namespace App\Filament\Resources\MeioFioResource\Pages;

use App\Filament\Resources\MeioFioResource;
use App\Services\Exports\MeioFioExportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;

class ListMeiosFio extends ListRecords
{
    protected static string $resource = MeioFioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn (MeioFioExportService $service, $livewire) => $service->exportToExcel($livewire->getFilteredTableQuery()->with('logradouro')->get())),
                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(fn (MeioFioExportService $service, $livewire) => $service->exportToPdf($livewire->getFilteredTableQuery()->with('logradouro')->get())),
            ])
                ->label('Exportar')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('gray'),

            Actions\Action::make('novo_meio_fio')
                ->label('Novo Meio-fio / Calçada')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Forma de Cadastro Geográfico')
                ->modalDescription('Como deseja registrar este trecho?')
                ->modalSubmitActionLabel('Continuar')
                ->modalWidth('md')
                ->form([
                    Forms\Components\Radio::make('metodo')
                        ->hiddenLabel()
                        ->options([
                            'mapa'    => '🗺️ Desenhar a linha no Mapa Interativo',
                            'geojson' => '💻 Preencher manualmente / Importar GeoJSON',
                        ])
                        ->default('mapa')
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($data['metodo'] === 'mapa') {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return redirect()->to(url('/app/' . $tenant->slug . '/mapa-interativo?layer=meio_fios&action=create'));
                    }
                    return redirect()->to(MeioFioResource::getUrl('create'));
                }),
        ];
    }
}
