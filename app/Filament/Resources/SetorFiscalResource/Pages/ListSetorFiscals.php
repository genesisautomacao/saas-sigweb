<?php

namespace App\Filament\Resources\SetorFiscalResource\Pages;

use App\Filament\Resources\SetorFiscalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;

class ListSetorFiscals extends ListRecords
{
    protected static string $resource = SetorFiscalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\PgvExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $setores = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportSetoresToExcel($setores);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\PgvExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $setores = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportSetoresToPdf($setores);
                    }),
            ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray'),

            Actions\Action::make('novo_setor')
                ->label('Novo Setor Fiscal')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Forma de Desenho do Setor')
                ->modalDescription('Como deseja demarcar o polígono deste Setor Fiscal?')
                ->modalSubmitActionLabel('Continuar')
                ->modalWidth('md')
                ->form([
                    Forms\Components\Radio::make('metodo')
                        ->hiddenLabel()
                        ->options([
                            'mapa' => '🗺️ Desenhar o Polígono no Mapa Interativo',
                            'geojson' => '💻 Importar Coordenadas (Colar GeoJSON)',
                        ])
                        ->default('mapa')
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($data['metodo'] === 'mapa') {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        // Redireciona para o mapa avisando que a layer alvo é setores_fiscais
                        $mapUrl = url('/app/' . $tenant->slug . '/mapa-interativo?layer=setores_fiscais&action=create');
                        return redirect()->to($mapUrl);
                    } else {
                        return redirect()->to(SetorFiscalResource::getUrl('create'));
                    }
                }),
        ];
    }
}