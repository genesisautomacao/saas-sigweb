<?php

namespace App\Filament\Resources\QuadraCemiterioResource\Pages;

use App\Filament\Resources\QuadraCemiterioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;

class ListQuadraCemiterios extends ListRecords
{
    protected static string $resource = QuadraCemiterioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 🛑 BOTÃO DE EXPORTAR
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\QuadraCemiterioExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $quadras = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($quadras);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\QuadraCemiterioExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $quadras = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($quadras);
                    }),
            ])
            ->label('Exportar')
            ->icon('heroicon-m-arrow-down-tray')
            ->button()
            ->color('gray'),

            // 🛑 BOTÃO DE CRIAR (HÍBRIDO)
            Actions\Action::make('nova_quadra')
                ->label('Nova Quadra')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Forma de Cadastro Geográfico')
                ->modalDescription('Como você deseja demarcar o polígono desta Quadra?')
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
                        // Redireciona para o mapa ativando a camada de quadras do cemitério
                        $mapUrl = url('/app/' . $tenant->slug . '/mapa-interativo?layer=quadras_cemiterio&action=create');
                        return redirect()->to($mapUrl);
                    } else {
                        return redirect()->to(QuadraCemiterioResource::getUrl('create'));
                    }
                }),
        ];
    }
}