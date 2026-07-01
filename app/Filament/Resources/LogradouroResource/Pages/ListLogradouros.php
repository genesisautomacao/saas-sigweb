<?php
namespace App\Filament\Resources\LogradouroResource\Pages;
use App\Filament\Resources\LogradouroResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Services\Exports\LogradouroExportService;

class ListLogradouros extends ListRecords
{
    protected static string $resource = LogradouroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')->label('Exportar Excel')->icon('heroicon-o-table-cells')
                    ->action(fn(LogradouroExportService $service) => $service->exportToExcel($this->getFilteredTableQuery()->get())),
                Actions\Action::make('export_pdf')->label('Exportar PDF')->icon('heroicon-o-document-text')
                    ->action(fn(LogradouroExportService $service) => $service->exportToPdf($this->getFilteredTableQuery()->get())),
                Actions\Action::make('export_xml')->label('Exportar XML')->icon('heroicon-o-code-bracket')
                    ->action(fn(LogradouroExportService $service) => $service->exportToXml($this->getFilteredTableQuery()->get())),
            ])->label('Exportar')->icon('heroicon-m-arrow-down-tray')->button()->color('gray'),

           \Filament\Actions\Action::make('novo_logradouro')
                ->label('Novo Logradouro')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Forma de Cadastro Geográfico')
                ->modalDescription('Como deseja demarcar a linha deste Logradouro?')
                ->modalSubmitActionLabel('Continuar')
                ->modalWidth('md')
                ->form([
                    \Filament\Forms\Components\Radio::make('metodo')
                        ->hiddenLabel()
                        ->options([
                            'mapa' => '🗺️ Desenhar a Linha no Mapa Interativo',
                            'geojson' => '💻 Importar Topografia (Colar GeoJSON)',
                        ])
                        ->default('mapa')
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($data['metodo'] === 'mapa') {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        // Redireciona para o mapa passando a ação de criar
                        $mapUrl = url('/app/' . $tenant->slug . '/mapa-interativo?layer=logradouros&action=create');
                        return redirect()->to($mapUrl);
                    } else {
                        // Vai para o formulário padrão do Backoffice colar o texto
                        return redirect()->to(\App\Filament\Resources\LogradouroResource::getUrl('create'));
                    }
                }),
        ];
    }
}