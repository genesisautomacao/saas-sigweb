<?php

namespace App\Filament\Resources\PosteResource\Pages;

use App\Filament\Resources\PosteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;

class ListPostes extends ListRecords
{
    protected static string $resource = PosteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 🛑 BOTÃO DE EXPORTAR (Alinhado à esquerda)
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, \App\Services\Exports\PosteExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para Excel')->info()->send();
                        $postes = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToExcel($postes);
                    }),

                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($livewire, \App\Services\Exports\PosteExportService $exportService) {
                        \Filament\Notifications\Notification::make()->title('Exportando para PDF')->info()->send();
                        $postes = $livewire->getFilteredTableQuery()->get();
                        return $exportService->exportToPdf($postes);
                    }),
            ])
                ->label('Exportar')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('gray'),

            // 🛑 BOTÃO DE CRIAR
            Actions\Action::make('novo_geografico')
                ->label('Novo Poste')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Localização do Novo Poste')
                ->modalDescription('Como você deseja definir o local deste ponto de luz?')
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
                        $mapUrl = url('/app/' . $tenant->slug . '/mapa-interativo?layer=postes&action=create');
                        return redirect()->to($mapUrl);
                    } else {
                        $url = PosteResource::getUrl('create') . '?lat=' . $data['latitude'] . '&lon=' . $data['longitude'];
                        return redirect()->to($url);
                    }
                }),
        ];
    }
}