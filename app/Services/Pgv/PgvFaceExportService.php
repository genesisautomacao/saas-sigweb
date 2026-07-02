<?php

namespace App\Services\Pgv;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Relatório das Faces de Quadra (item PoC 236) — código da seção, logradouro,
 * quadra, zona e valor calculado na PGV. Excel/PDF/XML.
 */
class PgvFaceExportService
{
    private function linha($f): array
    {
        return [
            'Codigo'    => $f->code ?? '-',
            'Quadra'    => $f->quadra->name ?? '-',
            'Logradouro' => $f->logradouro->name ?? '-',
            'Zona'      => $f->zona->sigla ?? ($f->zona->name ?? '-'),
            'ExtensaoM' => (float) ($f->extensao_geo ?? 0),
            'ValorM2'   => (float) ($f->valor_m2_calculado ?? 0),
        ];
    }

    public function exportToExcel(Collection $faces)
    {
        $fileName = 'faces-quadra-pgv-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
        $filePath = $path . $fileName;

        $data = $faces->map(fn($f) => $this->linha($f));
        $header = ['Codigo', 'Quadra', 'Logradouro', 'Zona', 'ExtensaoM', 'ValorM2'];
        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? array_fill_keys($header, null)))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $faces)
    {
        $fileName = 'faces-quadra-pgv-' . now()->format('Y-m-d-His') . '.pdf';
        $headings = ['Código', 'Quadra', 'Logradouro', 'Zona', 'Extensão (m)', 'Valor m² (PGV)'];

        $data = $faces->map(fn($f) => [
            $f->code ?? '-',
            $f->quadra->name ?? '-',
            $f->logradouro->name ?? '-',
            $f->zona->sigla ?? ($f->zona->name ?? '-'),
            number_format((float) ($f->extensao_geo ?? 0), 2, ',', '.'),
            'R$ ' . number_format((float) ($f->valor_m2_calculado ?? 0), 2, ',', '.'),
        ]);

        $title = 'Relatório PGV — Valores por Face de Quadra';
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'))->setPaper('a4', 'landscape');

        return response()->streamDownload(fn() => print($pdf->stream()), $fileName);
    }

    public function exportToXml(Collection $faces)
    {
        $fileName = 'faces-quadra-pgv-' . now()->format('Y-m-d-His') . '.xml';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><faces/>');

        foreach ($faces as $f) {
            $item = $xml->addChild('face');
            $item->addAttribute('id', (string) $f->sequential_id);
            foreach ($this->linha($f) as $k => $v) {
                $item->addChild(lcfirst($k), htmlspecialchars((string) $v));
            }
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }
}
