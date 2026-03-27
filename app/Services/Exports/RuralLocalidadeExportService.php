<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class RuralLocalidadeExportService
{
    public function exportToExcel(Collection $records)
    {
        $fileName = 'localidades-rurais-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $records->map(function ($record) {
            return [
                'ID' => $record->sequential_id,
                'Nome' => $record->nome,
                'Tipo' => $record->tipo,
                'Área Geo (m²)' => $record->area_geo ? number_format($record->area_geo, 2, ',', '') : '0,00',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $records)
    {
        $fileName = 'localidades-rurais-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Nome', 'Tipo', 'Área Geo (m²)'];

        $data = $records->map(function ($record) {
            return [
                $record->sequential_id,
                $record->nome,
                $record->tipo,
                $record->area_geo ? number_format($record->area_geo, 2, ',', '') : '0,00',
            ];
        });

        $title = 'Relatório de Localidades e Distritos Rurais';

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}