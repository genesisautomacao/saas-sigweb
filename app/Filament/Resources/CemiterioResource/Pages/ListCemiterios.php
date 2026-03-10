<?php

namespace App\Filament\Resources\CemiterioResource\Pages;

use App\Filament\Resources\CemiterioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;

class ListCemiterios extends ListRecords
{
    protected static string $resource = CemiterioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 🛑 BOTÃO DE EXPORTAR
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\CemiterioExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $cemiterios = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($cemiterios);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\CemiterioExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $cemiterios = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($cemiterios);
                    }),
            ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray'),

            // 🛑 BOTÃO DE CRIAR (HÍBRIDO)
            Actions\Action::make('novo_cemiterio')
                ->label('Novo Cemitério')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Forma de Cadastro Geográfico')
                ->modalDescription('Como você deseja demarcar o polígono deste Cemitério?')
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
                        // Manda para o mapa ativando a camada cemiterios e forçando a ação de criar
                        $mapUrl = url('/app/' . $tenant->slug . '/mapa-interativo?layer=cemiterios&action=create');
                        return redirect()->to($mapUrl);
                    } else {
                        // Abre a tela normal de formulário para colar o texto
                        return redirect()->to(CemiterioResource::getUrl('create'));
                    }
                }),
        ];
    }
}