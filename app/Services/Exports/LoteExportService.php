<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class LoteExportService
{
    public function exportToExcel(Collection $lotes)
    {
        $fileName = 'lotes-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $lotes->map(function ($lote) {
            return [
                'ID' => $lote->sequential_id,
                'Número do Lote' => $lote->numero_lote,
                'Quadra' => $lote->quadra->name ?? '-',
                'Zona' => $lote->zona->sigla ?? '-',
                'Testada (m)' => $lote->main_facade_length ? number_format($lote->main_facade_length, 2, ',', '') : '0,00',
                'Área Geo (m²)' => $lote->area_geo ? number_format($lote->area_geo, 2, ',', '') : '0,00',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $lotes)
    {
        $fileName = 'lotes-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Lote', 'Quadra', 'Zona', 'Testada (m)', 'Área (m²)'];

        $data = $lotes->map(function ($lote) {
            return [
                $lote->sequential_id,
                $lote->numero_lote,
                $lote->quadra->name ?? '-',
                $lote->zona->sigla ?? '-',
                $lote->main_facade_length ? number_format($lote->main_facade_length, 2, ',', '') : '0,00',
                $lote->area_geo ? number_format($lote->area_geo, 2, ',', '') : '0,00',
            ];
        });

        $title = 'Relatório de Lotes e Terrenos';

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}