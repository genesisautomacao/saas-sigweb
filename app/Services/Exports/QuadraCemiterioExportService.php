<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class QuadraCemiterioExportService
{
    public function exportToExcel(Collection $quadras)
    {
        $fileName = 'quadras_cemiterio-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $quadras->map(function ($quadra) {
            return [
                'ID' => $quadra->sequential_id,
                'Cemitério' => $quadra->cemiterio->name ?? 'Não Informado',
                'Nome/Número da Quadra' => $quadra->name ?? 'S/N',
                'Área (m²)' => $quadra->area_geo ? number_format($quadra->area_geo, 2, ',', '') : '0.00',
                'Criado em' => $quadra->created_at ? $quadra->created_at->format('d/m/Y') : '-',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $quadras)
    {
        $fileName = 'quadras_cemiterio-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Cemitério', 'Quadra', 'Área (m²)', 'Cadastro'];

        $data = $quadras->map(function ($quadra) {
            return [
                $quadra->sequential_id,
                $quadra->cemiterio->name ?? 'Não Informado',
                $quadra->name ?? 'S/N',
                $quadra->area_geo ? number_format($quadra->area_geo, 2, ',', '') : '0.00',
                $quadra->created_at ? $quadra->created_at->format('d/m/Y') : '-',
            ];
        });

        $title = 'Relatório de Quadras de Cemitério';

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}