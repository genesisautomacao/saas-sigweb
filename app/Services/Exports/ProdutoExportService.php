<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class ProdutoExportService
{
    public function exportToExcel(Collection $produtos)
    {
        $fileName = 'produtos-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $produtos->map(function ($produto) {
            return [
                'ID' => $produto->sequential_id,
                'Nome' => $produto->name ?? 'Não Identificada',
                'SKU' => $produto->sku ?? 'S/N',
                'Descrição' => $produto->description ?? 'S/N',
                'Unidade' => $produto->unit ?? 'S/N'
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $produtos)
    {
        $fileName = 'produtos-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Nome', 'Sku', 'descrição', 'Unidade'];

        $data = $produtos->map(function ($produto) {
            return [
                $produto->sequential_id,
                $produto->name ?? 'Não Identificada',
                $produto->sku ?? 'S/N',
                $produto->description ?? 'S/N',
                $produto->unit ?? 'S/N'
            ];
        });

        $title = 'Relatório de Produtos';

        // Usa a view global de PDF
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}