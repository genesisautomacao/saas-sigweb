<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class ArvoreExportService
{
    public function exportToExcel(Collection $arvores)
    {
        $fileName = 'arvores-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $arvores->map(function ($arvore) {
            return [
                'ID' => $arvore->sequential_id,
                'Espécie Botânica' => $arvore->botanical_species ?? 'Não Identificada',
                'Referência/Endereço' => $arvore->address ?? 'S/N',
                'Porte' => ucfirst($arvore->size ?? '-'),
                'Saúde (Fitossanitária)' => $arvore->phytosanitary_condition ?? '-',
                'Risco' => $arvore->risk_potential ?? '-',
                'DAP (cm)' => $arvore->trunk_diameter_dap ?? '-',
                'Altura Total (m)' => $arvore->total_height ?? '-',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $arvores)
    {
        $fileName = 'arvores-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Espécie', 'Endereço', 'Porte', 'Saúde', 'Risco'];

        $data = $arvores->map(function ($arvore) {
            return [
                $arvore->sequential_id,
                $arvore->botanical_species ?? 'Não Identificada',
                $arvore->address ?? 'S/N',
                ucfirst($arvore->size ?? '-'),
                $arvore->phytosanitary_condition ?? '-',
                $arvore->risk_potential ?? '-',
            ];
        });

        $title = 'Relatório de Arborização Urbana';

        // Usa a view global de PDF
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}