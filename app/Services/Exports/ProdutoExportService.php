<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ProdutoExportService
{
    public function exportToExcel(Collection $produtos)
    {
        $fileName = 'produtos-'.now()->format('Y-m-d-His').'.xlsx';
        $path = storage_path('app/exports/');

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path.$fileName;

        $data = $produtos->map(function ($produto) {
            return [
                'ID' => $produto->sequential_id,
                'Nome' => $produto->name ?? 'Não Identificada',
                'SKU' => $produto->sku ?? 'S/N',
                'Descrição' => $produto->description ?? 'S/N',
                'Unidade' => $produto->unit ?? 'S/N',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $produtos)
    {
        $fileName = 'produtos-'.now()->format('Y-m-d-His').'.pdf';

        $headings = ['ID', 'Nome', 'Sku', 'descrição', 'Unidade'];

        $data = $produtos->map(function ($produto) {
            return [
                $produto->sequential_id,
                $produto->name ?? 'Não Identificada',
                $produto->sku ?? 'S/N',
                $produto->description ?? 'S/N',
                $produto->unit ?? 'S/N',
            ];
        });

        $title = 'Relatório de Produtos';

        // Usa a view global de PDF
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }

    private function linha($produto): array
    {
        return [
            'ID' => $produto->sequential_id,
            'Nome' => $produto->name ?? '-',
            'SKU' => $produto->sku ?? '-',
            'Descricao' => $produto->description ?? '-',
            'Unidade' => $produto->unit ?? '-',
        ];
    }

    public function exportToCsv(Collection $produtos)
    {
        $fileName = 'produtos-'.now()->format('Y-m-d-His').'.csv';
        $path = storage_path('app/exports/');
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
        $filePath = $path.$fileName;

        $data = $produtos->map(fn ($p) => $this->linha($p));
        SimpleExcelWriter::create($filePath, 'csv')
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToXml(Collection $produtos)
    {
        $fileName = 'produtos-'.now()->format('Y-m-d-His').'.xml';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><produtos/>');

        foreach ($produtos as $produto) {
            $item = $xml->addChild('produto');
            foreach ($this->linha($produto) as $k => $v) {
                $item->addChild($k, htmlspecialchars((string) $v));
            }
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }
}
