<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Relatório de Garantia de Produto (item PoC 059) — lotes/séries com
 * data de garantia, filtrável por local/tipo, produto e família.
 */
class LoteEstoqueExportService
{
    private function situacao($lote): string
    {
        $d = $lote->dias_garantia;
        if ($d === null) return 'Sem garantia';
        if ($d < 0) return 'Vencida há ' . abs($d) . ' dia(s)';
        if ($d <= 30) return 'Vence em ' . $d . ' dia(s)';
        return 'Vigente (' . $d . ' dia(s))';
    }

    private function linha($l): array
    {
        return [
            'Lote/Série'   => $l->numero_lote,
            'Produto'      => $l->produto->name ?? '-',
            'Fornecedor'   => $l->fornecedor->name ?? '-',
            'Qtd Inicial'  => (float) $l->quantidade_inicial,
            'Fabricação'   => $l->data_fabricacao?->format('d/m/Y') ?? '-',
            'Validade'     => $l->data_validade?->format('d/m/Y') ?? '-',
            'Garantia até' => $l->data_garantia?->format('d/m/Y') ?? '-',
            'Situação'     => $this->situacao($l),
        ];
    }

    public function exportToExcel(Collection $records)
    {
        $fileName = 'garantia-lotes-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
        $filePath = $path . $fileName;

        $data = $records->map(fn($l) => $this->linha($l));
        $header = ['Lote/Série', 'Produto', 'Fornecedor', 'Qtd Inicial', 'Fabricação', 'Validade', 'Garantia até', 'Situação'];

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? array_fill_keys($header, null)))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $records)
    {
        $fileName = 'garantia-lotes-' . now()->format('Y-m-d-His') . '.pdf';
        $headings = ['Lote/Série', 'Produto', 'Fornecedor', 'Validade', 'Garantia até', 'Situação'];

        $data = $records->map(fn($l) => [
            $l->numero_lote,
            $l->produto->name ?? '-',
            $l->fornecedor->name ?? '-',
            $l->data_validade?->format('d/m/Y') ?? '-',
            $l->data_garantia?->format('d/m/Y') ?? '-',
            $this->situacao($l),
        ]);

        $title = 'Relatório de Garantia de Produtos';
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(fn() => print($pdf->stream()), $fileName);
    }

    public function exportToXml(Collection $records)
    {
        $fileName = 'garantia-lotes-' . now()->format('Y-m-d-His') . '.xml';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><lotes/>');

        foreach ($records as $l) {
            $item = $xml->addChild('lote');
            $item->addAttribute('id', (string) $l->sequential_id);
            $item->addChild('numero_lote', htmlspecialchars($l->numero_lote));
            $item->addChild('produto', htmlspecialchars($l->produto->name ?? ''));
            $item->addChild('fornecedor', htmlspecialchars($l->fornecedor->name ?? ''));
            $item->addChild('quantidade_inicial', (string) $l->quantidade_inicial);
            $item->addChild('data_fabricacao', $l->data_fabricacao?->format('Y-m-d') ?? '');
            $item->addChild('data_validade', $l->data_validade?->format('Y-m-d') ?? '');
            $item->addChild('data_garantia', $l->data_garantia?->format('Y-m-d') ?? '');
            $item->addChild('situacao', htmlspecialchars($this->situacao($l)));
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }
}
