<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class JazigoExportService
{
    public function exportToExcel(Collection $jazigos)
    {
        $fileName = 'jazigos-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $jazigos->map(function ($jazigo) {
            return [
                'ID' => $jazigo->sequential_id,
                'Cemitério' => $jazigo->quadraCemiterio->cemiterio->name ?? '-',
                'Quadra' => $jazigo->quadraCemiterio->name ?? '-',
                'Código do Jazigo' => $jazigo->codigo,
                'Tipo' => ucfirst($jazigo->tipo ?? '-'),
                'Status' => ucfirst($jazigo->status),
                'Proprietário' => $jazigo->proprietario->name ?? 'Sem Proprietário',
                'Área (m²)' => $jazigo->area_geo ? number_format($jazigo->area_geo, 2, ',', '') : '0.00',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $jazigos)
    {
        $fileName = 'jazigos-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Quadra', 'Código', 'Status', 'Proprietário', 'Área (m²)'];

        $data = $jazigos->map(function ($jazigo) {
            return [
                $jazigo->sequential_id,
                $jazigo->quadraCemiterio->name ?? '-',
                $jazigo->codigo,
                ucfirst($jazigo->status),
                $jazigo->proprietario->name ?? 'Nenhum',
                $jazigo->area_geo ? number_format($jazigo->area_geo, 2, ',', '') : '0.00',
            ];
        });

        $title = 'Relatório de Jazigos e Túmulos';

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}