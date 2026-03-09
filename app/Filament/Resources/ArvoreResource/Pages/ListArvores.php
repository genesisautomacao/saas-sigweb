<?php

namespace App\Filament\Resources\ArvoreResource\Pages;

use App\Filament\Resources\ArvoreResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;

class ListArvores extends ListRecords
{
    protected static string $resource = ArvoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 🛑 BOTÃO DE EXPORTAR
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\ArvoreExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $arvores = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($arvores);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\ArvoreExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $arvores = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($arvores);
                    }),
            ])
                ->label('Exportar')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('gray'),

            // 🛑 BOTÃO DE CRIAR
            Actions\Action::make('nova_arvore')
                ->label('Nova Árvore')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Localização da Nova Árvore')
                ->modalDescription('Como você deseja definir o local deste indivíduo arbóreo?')
                ->modalSubmitActionLabel('Continuar')
                ->modalWidth('md')
                ->form([
                    Forms\Components\Radio::make('metodo')
                        ->hiddenLabel()
                        ->options([
                            'mapa' => '🗺️ Capturar pelo Mapa Interativo',
                            'coordenadas' => '📍 Digitar Coordenadas Manualmente',
                        ])
                        ->default('mapa')
                        ->required()
                        ->live(),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('latitude')
                                ->label('Latitude (Y)')
                                ->numeric()
                                ->required(),
                            Forms\Components\TextInput::make('longitude')
                                ->label('Longitude (X)')
                                ->numeric()
                                ->required(),
                        ])
                        ->visible(fn (Forms\Get $get) => $get('metodo') === 'coordenadas'),
                ])
                ->action(function (array $data) {
                    if ($data['metodo'] === 'mapa') {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        $mapUrl = url('/app/' . $tenant->slug . '/mapa-interativo?layer=arvores&action=create');
                        return redirect()->to($mapUrl);
                    } else {
                        $url = ArvoreResource::getUrl('create') . '?lat=' . $data['latitude'] . '&lon=' . $data['longitude'];
                        return redirect()->to($url);
                    }
                }),
        ];
    }
}