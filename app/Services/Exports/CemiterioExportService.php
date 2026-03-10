<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class CemiterioExportService
{
    public function exportToExcel(Collection $cemiterios)
    {
        $fileName = 'cemiterios-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $cemiterios->map(function ($cemiterio) {
            return [
                'ID' => $cemiterio->sequential_id,
                'Nome' => $cemiterio->name ?? 'Não Informado',
                'Endereço' => $cemiterio->address ?? 'S/N',
                'Área (m²)' => $cemiterio->area_geo ? number_format($cemiterio->area_geo, 2, ',', '') : '0.00',
                'Criado em' => $cemiterio->created_at ? $cemiterio->created_at->format('d/m/Y') : '-',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $cemiterios)
    {
        $fileName = 'cemiterios-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Nome', 'Endereço', 'Área (m²)', 'Cadastro'];

        $data = $cemiterios->map(function ($cemiterio) {
            return [
                $cemiterio->sequential_id,
                $cemiterio->name ?? 'Não Informado',
                $cemiterio->address ?? 'S/N',
                $cemiterio->area_geo ? number_format($cemiterio->area_geo, 2, ',', '') : '0.00',
                $cemiterio->created_at ? $cemiterio->created_at->format('d/m/Y') : '-',
            ];
        });

        $title = 'Relatório de Gestão de Cemitérios';

        // Usa a view global de PDF do seu sistema
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}