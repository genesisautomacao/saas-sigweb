<?php

namespace App\Filament\Resources\SetorCadastralResource\Pages;

use App\Filament\Resources\SetorCadastralResource;
use App\Models\SetorCadastral;
use App\Services\Exports\SetorCadastralExportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSetorCadastrais extends ListRecords
{
    protected static string $resource = SetorCadastralResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn (SetorCadastralExportService $service, $livewire) => $service->exportToExcel($livewire->getFilteredTableQuery()->with('bairros')->get())),
                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(fn (SetorCadastralExportService $service, $livewire) => $service->exportToPdf($livewire->getFilteredTableQuery()->with('bairros')->get())),
                Actions\Action::make('export_csv')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-document')
                    ->action(fn (SetorCadastralExportService $service, $livewire) => $service->exportToCsv($livewire->getFilteredTableQuery()->with('bairros')->get())),
                Actions\Action::make('export_xml')
                    ->label('Exportar XML')
                    ->icon('heroicon-o-code-bracket')
                    ->action(fn (SetorCadastralExportService $service, $livewire) => $service->exportToXml($livewire->getFilteredTableQuery()->with('bairros')->get())),
            ])
                ->label('Exportar')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('gray'),

            // Todos os setores de uma vez — o caminho normal logo após importar setores e bairros
            Actions\Action::make('vincular_todos')
                ->label('Vincular bairros (todos os setores)')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('create', SetorCadastral::class) ?? false)
                ->modalDescription('Cada bairro é ligado ao setor que contém o seu ponto interno.')
                ->form([
                    Forms\Components\Toggle::make('sobrescrever')
                        ->label('Trocar também bairros que já estão em outro setor')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    $n = 0;
                    foreach (SetorCadastral::whereNotNull('geo')->get() as $setor) {
                        $n += $setor->vincularBairrosPelaGeometria((bool) ($data['sobrescrever'] ?? false));
                    }
                    Notification::make()->title("{$n} bairro(s) vinculado(s)")->success()->send();
                }),

            Actions\Action::make('novo_setor')
                ->label('Novo Setor Cadastral')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->visible(fn () => auth()->user()?->can('create', SetorCadastral::class) ?? false)
                ->modalHeading('Forma de Cadastro Geográfico')
                ->modalDescription('Como deseja registrar a delimitação deste setor?')
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

                        return redirect()->to(url('/app/'.$tenant->slug.'/mapa-interativo?layer=setores_cadastrais&action=create'));
                    }

                    return redirect()->to(SetorCadastralResource::getUrl('create'));
                }),
        ];
    }
}
