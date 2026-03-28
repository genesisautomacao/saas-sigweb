<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class PatrimonioPublicoExportService
{
    public function exportToExcel(Collection $records)
    {
        $fileName = 'patrimonios-publicos-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $records->map(function ($record) {
            return [
                'ID' => $record->sequential_id,
                'Nome do Patrimônio' => $record->name,
                'Tipo' => $record->tipo->name ?? '-',
                'Endereço' => $record->address ?? '-',
                'Descrição' => $record->description ?? '-',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $records)
    {
        $fileName = 'patrimonios-publicos-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Nome', 'Tipo', 'Endereço', 'Descrição'];

        $data = $records->map(function ($record) {
            return [
                $record->sequential_id,
                $record->name,
                $record->tipo->name ?? '-',
                $record->address ?? '-',
                $record->description ?? '-',
            ];
        });

        $title = 'Relatório de Patrimônios Públicos';

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}