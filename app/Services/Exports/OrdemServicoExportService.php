<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class OrdemServicoExportService
{
    public function exportToExcel(Collection $ordens)
    {
        $fileName = 'ordens-servico-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) File::makeDirectory($path, 0755, true, true);
        $filePath = $path . $fileName;

        $data = $ordens->map(function ($os) {
            $artefato = $os->asset_type ? class_basename($os->asset_type) . ' #' . ($os->asset->sequential_id ?? '?') : '-';
            $equipe = $os->equipe->pluck('name')->implode(', ');

            return [
                'OS #' => $os->sequential_id,
                'Status' => strtoupper($os->status),
                'Artefato' => $artefato,
                'Prioridade' => ucfirst($os->prioridade),
                'Equipe' => $equipe ?: 'Não definida',
                'Abertura' => $os->created_at->format('d/m/Y'),
                'Conclusão' => $os->data_fim ? $os->data_fim->format('d/m/Y') : '-',
            ];
        });

        SimpleExcelWriter::create($filePath)->addHeader(array_keys($data->first() ?? []))->addRows($data->toArray());
        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $ordens)
    {
        $fileName = 'ordens-servico-' . now()->format('Y-m-d-His') . '.pdf';
        $headings = ['OS #', 'Status', 'Artefato', 'Prioridade', 'Equipe', 'Data'];

        $data = $ordens->map(function ($os) {
            $artefato = $os->asset_type ? class_basename($os->asset_type) . ' #' . ($os->asset->sequential_id ?? '?') : '-';
            return [
                $os->sequential_id, strtoupper($os->status), $artefato, ucfirst($os->prioridade), 
                $os->equipe->pluck('name')->implode(', ') ?: 'N/A', $os->created_at->format('d/m/Y')
            ];
        });

        $title = 'Relatório Geral de Ordens de Serviço';
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));
        return response()->streamDownload(fn () => print($pdf->stream()), $fileName);
    }
}