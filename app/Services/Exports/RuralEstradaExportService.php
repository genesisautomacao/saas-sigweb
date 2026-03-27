<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class RuralEstradaExportService
{
    public function exportToExcel(Collection $records)
    {
        $fileName = 'estradas-rurais-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $records->map(function ($record) {
            return [
                'ID' => $record->sequential_id,
                'Nome' => $record->nome,
                'Localidade' => $record->localidade->nome ?? '-',
                'Tipo' => $record->tipo,
                'Pavimento' => $record->tipo_pavimento ?? '-',
                'Condição' => $record->condicao_trafego ?? '-',
                'Extensão (m)' => $record->extensao_geo ? number_format($record->extensao_geo, 2, ',', '') : '0,00',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $records)
    {
        $fileName = 'estradas-rurais-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Nome', 'Localidade', 'Tipo', 'Pavimento', 'Condição', 'Extensão (m)'];

        $data = $records->map(function ($record) {
            return [
                $record->sequential_id,
                $record->nome,
                $record->localidade->nome ?? '-',
                $record->tipo,
                $record->tipo_pavimento ?? '-',
                $record->condicao_trafego ?? '-',
                $record->extensao_geo ? number_format($record->extensao_geo, 2, ',', '') : '0,00',
            ];
        });

        $title = 'Relatório de Estradas Rurais e Vicinais';

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}