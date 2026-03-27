<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class RuralPonteExportService
{
    public function exportToExcel(Collection $records)
    {
        $fileName = 'pontes-rurais-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $records->map(function ($record) {
            return [
                'ID' => $record->sequential_id,
                'Referência' => $record->nome_referencia ?? 'Sem Nome',
                'Localidade' => $record->localidade->nome ?? '-',
                'Estrada' => $record->estrada->nome ?? '-',
                'Material' => $record->material_construcao ?? '-',
                'Carga (Ton)' => $record->capacidade_carga_toneladas ?? '-',
                'Conservação' => $record->estado_conservacao ?? '-',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $records)
    {
        $fileName = 'pontes-rurais-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Referência', 'Localidade', 'Estrada', 'Material', 'Carga (Ton)', 'Conservação'];

        $data = $records->map(function ($record) {
            return [
                $record->sequential_id,
                $record->nome_referencia ?? 'Sem Nome',
                $record->localidade->nome ?? '-',
                $record->estrada->nome ?? '-',
                $record->material_construcao ?? '-',
                $record->capacidade_carga_toneladas ?? '-',
                $record->estado_conservacao ?? '-',
            ];
        });

        $title = 'Relatório de Pontes Rurais';

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}