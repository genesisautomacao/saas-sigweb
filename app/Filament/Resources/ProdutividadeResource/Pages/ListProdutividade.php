<?php

namespace App\Filament\Resources\ProdutividadeResource\Pages;

use App\Filament\Resources\ProdutividadeResource;
use App\Services\Exports\ProdutividadeExportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProdutividade extends ListRecords
{
    protected static string $resource = ProdutividadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('export_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-table-cells')
                    ->action(function ($livewire, ProdutividadeExportService $exportService) {
                        \Filament\Notifications\Notification::make()
                            ->title('Exportando para Excel')
                            ->info()
                            ->send();
                        $lotes = $livewire->getFilteredTableQuery()
                            ->with(['quadra', 'zona', 'coletaVigente.coletadoPor', 'unidadesImobiliarias', 'edificacoes'])
                            ->get();
                        return $exportService->exportToExcel($lotes);
                    }),

                // R84-5 — até LOTES_POR_PDF sai na hora; acima vai para a fila (ZIP de PDFs,
                // aviso no sininho e em "Arquivos Gerados"). Medição na VPS: 2.498 lotes
                // num PDF só = ~400 MB / ~5 GB de memória — nunca terminava.
                Actions\Action::make('export_pdf')
                    ->label('Exportar PDF Detalhado')
                    ->icon('heroicon-o-document-text')
                    ->modalHeading('PDF detalhado das coletas')
                    ->modalSubmitActionLabel(fn ($livewire) => $this->totalFiltrado($livewire) > ProdutividadeExportService::LOTES_POR_PDF ? 'Gerar em segundo plano' : 'Gerar PDF')
                    ->form(fn ($livewire) => [
                        \Filament\Forms\Components\Placeholder::make('resumo')
                            ->hiddenLabel()
                            ->content(function () use ($livewire) {
                                $total = $this->totalFiltrado($livewire);
                                $teto = ProdutividadeExportService::LOTES_POR_PDF;

                                return $total > $teto
                                    ? "{$total} lotes no filtro atual. Acima de {$teto} o relatório é gerado em segundo plano: você recebe um ZIP com PDFs de até {$teto} lotes cada, com aviso no sininho e em \"Arquivos Gerados\"."
                                    : "{$total} lote(s) no filtro atual — o PDF é gerado agora.";
                            }),
                        \Filament\Forms\Components\Select::make('agrupamento')
                            ->label('Dividir os PDFs')
                            ->options(\App\Jobs\GerarPdfColetasJob::AGRUPAMENTOS)
                            ->default('coletor')
                            ->required()
                            ->visible(fn () => $this->totalFiltrado($livewire) > ProdutividadeExportService::LOTES_POR_PDF),
                    ])
                    ->action(function (array $data, $livewire, ProdutividadeExportService $exportService) {
                        $query = $livewire->getFilteredTableQuery();

                        if ($this->totalFiltrado($livewire) <= ProdutividadeExportService::LOTES_POR_PDF) {
                            $lotes = $query
                                ->with(['quadra', 'zona', 'coletaVigente.coletadoPor', 'unidadesImobiliarias', 'edificacoes'])
                                ->get();

                            return $exportService->exportToPdf($lotes);
                        }

                        $tenant = \Filament\Facades\Filament::getTenant();
                        $arquivo = \App\Services\ArquivosGerados\ArquivosGeradosService::registrar(
                            'coletas_pdf_detalhado',
                            'PDF detalhado de coletas',
                            [
                                'lote_ids' => $query->pluck('lotes.id')->map(fn ($id) => (int) $id)->all(),
                                'agrupamento' => $data['agrupamento'] ?? 'coletor',
                            ],
                            auth()->user(),
                            $tenant,
                        );
                        \App\Jobs\GerarPdfColetasJob::dispatch($arquivo->id);

                        \Filament\Notifications\Notification::make()
                            ->title('PDF detalhado em processamento')
                            ->body('Você será avisado no sininho quando o ZIP estiver pronto. Acompanhe em Configurações → Arquivos Gerados.')
                            ->success()
                            ->send();
                    }),
            ])
                ->label('Exportar')
                ->icon('heroicon-m-arrow-down-tray')
                ->button()
                ->color('gray'),
        ];
    }

    /** Lotes no filtro atual da tabela (memorizado por requisição — o modal pergunta várias vezes). */
    private ?int $totalFiltradoCache = null;

    private function totalFiltrado($livewire): int
    {
        return $this->totalFiltradoCache ??= (int) $livewire->getFilteredTableQuery()->count();
    }
}
