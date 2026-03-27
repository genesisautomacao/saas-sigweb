<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class RuralPropriedadeExportService
{
    public function exportToExcel(Collection $records)
    {
        $fileName = 'propriedades-rurais-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $records->map(function ($record) {
            return [
                'ID' => $record->sequential_id,
                'Nome / Fazenda' => $record->nome_propriedade,
                'Localidade' => $record->localidade->nome ?? '-',
                'Proprietário' => $record->proprietario->name ?? '-',
                'INCRA' => $record->codigo_incra ?? '-',
                'CAR' => $record->codigo_car ?? '-',
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
        $fileName = 'propriedades-rurais-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Nome / Fazenda', 'Localidade', 'Proprietário', 'INCRA', 'CAR', 'Área Geo (m²)'];

        $data = $records->map(function ($record) {
            return [
                $record->sequential_id,
                $record->nome_propriedade,
                $record->localidade->nome ?? '-',
                $record->proprietario->name ?? '-',
                $record->codigo_incra ?? '-',
                $record->codigo_car ?? '-',
                $record->area_geo ? number_format($record->area_geo, 2, ',', '') : '0,00',
            ];
        });

        $title = 'Relatório de Propriedades Rurais (INCRA/CAR)';

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}