<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Relatório de Saldo em Estoque (item PoC 058) — geral e por lote,
 * filtrável por local, tipo de estoque, produto e família.
 */
class EstoqueExportService
{
    private function linha($e): array
    {
        return [
            'Local'   => $e->localEstoque->name ?? '-',
            'Tipo'    => $e->tipoEstoque->name ?? '-',
            'Produto' => $e->produto->name ?? '-',
            'Família' => $e->produto->familia->name ?? '-',
            'Lote'    => $e->loteEstoque->numero_lote ?? '-',
            'SKU'     => $e->produto->sku ?? '-',
            'Saldo'   => (float) $e->quantity,
            'UN'      => $e->produto->unit ?? '-',
        ];
    }

    public function exportToExcel(Collection $records)
    {
        $fileName = 'saldo-estoque-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
        $filePath = $path . $fileName;

        $data = $records->map(fn($e) => $this->linha($e));
        $header = ['Local', 'Tipo', 'Produto', 'Família', 'Lote', 'SKU', 'Saldo', 'UN'];

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? array_fill_keys($header, null)))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $records)
    {
        $fileName = 'saldo-estoque-' . now()->format('Y-m-d-His') . '.pdf';
        $headings = ['Local', 'Tipo', 'Produto', 'Família', 'Lote', 'Saldo', 'UN'];

        $data = $records->map(fn($e) => [
            $e->localEstoque->name ?? '-',
            $e->tipoEstoque->name ?? '-',
            $e->produto->name ?? '-',
            $e->produto->familia->name ?? '-',
            $e->loteEstoque->numero_lote ?? '-',
            (float) $e->quantity,
            $e->produto->unit ?? '-',
        ]);

        $title = 'Relatório de Saldo em Estoque';
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(fn() => print($pdf->stream()), $fileName);
    }

    public function exportToXml(Collection $records)
    {
        $fileName = 'saldo-estoque-' . now()->format('Y-m-d-His') . '.xml';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><saldos/>');

        foreach ($records as $e) {
            $item = $xml->addChild('saldo');
            $item->addChild('local', htmlspecialchars($e->localEstoque->name ?? ''));
            $item->addChild('tipo_estoque', htmlspecialchars($e->tipoEstoque->name ?? ''));
            $item->addChild('produto', htmlspecialchars($e->produto->name ?? ''));
            $item->addChild('familia', htmlspecialchars($e->produto->familia->name ?? ''));
            $item->addChild('lote', htmlspecialchars($e->loteEstoque->numero_lote ?? ''));
            $item->addChild('sku', htmlspecialchars($e->produto->sku ?? ''));
            $item->addChild('saldo', (string) $e->quantity);
            $item->addChild('unidade', htmlspecialchars($e->produto->unit ?? ''));
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }
}
