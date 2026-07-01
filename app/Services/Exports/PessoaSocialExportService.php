<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Relatório de Pessoa - Social (item PoC 091) — XLS/PDF/CSV/XML.
 */
class PessoaSocialExportService
{
    private function linha($p): array
    {
        return [
            'ID'          => $p->sequential_id,
            'Nome'        => $p->name ?? '-',
            'CPF'         => $p->cpf ?? '-',
            'RG'          => $p->rg ?? '-',
            'NIS'         => $p->nis ?? '-',
            'PIS'         => $p->pis ?? '-',
            'Nascimento'  => $p->birth_date?->format('d/m/Y') ?? '-',
            'Telefone'    => $p->telefone ?? '-',
            'EstadoCivil' => $p->estado_civil ?? '-',
            'Sexo'        => $p->sexo ?? '-',
        ];
    }

    public function exportToExcel(Collection $pessoas)
    {
        return $this->writeSpreadsheet($pessoas, 'xlsx');
    }

    public function exportToCsv(Collection $pessoas)
    {
        return $this->writeSpreadsheet($pessoas, 'csv');
    }

    private function writeSpreadsheet(Collection $pessoas, string $ext)
    {
        $fileName = 'pessoas-social-' . now()->format('Y-m-d-His') . '.' . $ext;
        $path = storage_path('app/exports/');
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
        $filePath = $path . $fileName;

        $data = $pessoas->map(fn($p) => $this->linha($p));
        SimpleExcelWriter::create($filePath, $ext === 'csv' ? 'csv' : null)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $pessoas)
    {
        $fileName = 'pessoas-social-' . now()->format('Y-m-d-His') . '.pdf';
        $headings = ['ID', 'Nome', 'CPF', 'RG', 'NIS', 'Nascimento', 'Telefone'];

        $data = $pessoas->map(fn($p) => [
            $p->sequential_id,
            $p->name ?? '-',
            $p->cpf ?? '-',
            $p->rg ?? '-',
            $p->nis ?? '-',
            $p->birth_date?->format('d/m/Y') ?? '-',
            $p->telefone ?? '-',
        ]);

        $title = 'Relatório de Pessoas (Social)';
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(fn() => print($pdf->stream()), $fileName);
    }

    public function exportToXml(Collection $pessoas)
    {
        $fileName = 'pessoas-social-' . now()->format('Y-m-d-His') . '.xml';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><pessoas/>');

        foreach ($pessoas as $p) {
            $item = $xml->addChild('pessoa');
            $item->addAttribute('id', (string) $p->sequential_id);
            foreach ($this->linha($p) as $k => $v) {
                $item->addChild(lcfirst($k), htmlspecialchars((string) $v));
            }
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }
}
