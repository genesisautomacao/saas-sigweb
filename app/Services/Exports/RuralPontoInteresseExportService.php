<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

class RuralPontoInteresseExportService
{
    public function exportToExcel(Collection $records)
    {
        $fileName = 'pontos-interesse-rurais-'.now()->format('Y-m-d-His').'.xlsx';
        $path = storage_path('app/exports/');

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path.$fileName;

        $data = $records->map(function ($record) {
            return [
                'ID' => $record->sequential_id,
                'Nome' => $record->nome,
                'Categoria' => $record->categoria,
                'Localidade' => $record->localidade->nome ?? '-',
                'Observações' => $record->observacoes ?? '-',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $records)
    {
        $fileName = 'pontos-interesse-rurais-'.now()->format('Y-m-d-His').'.pdf';

        $headings = ['ID', 'Nome', 'Categoria', 'Localidade', 'Observações'];

        $data = $records->map(function ($record) {
            return [
                $record->sequential_id,
                $record->nome,
                $record->categoria,
                $record->localidade->nome ?? '-',
                $record->observacoes ?? '-',
            ];
        });

        $title = 'Relatório de Pontos de Interesse (Zona Rural)';

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }

    private function linha($record): array
    {
        return [
            'ID' => $record->sequential_id,
            'Nome' => $record->nome,
            'Categoria' => $record->categoria,
            'Localidade' => $record->localidade->nome ?? '-',
            'Observacoes' => $record->observacoes ?? '-',
        ];
    }

    public function exportToCsv(Collection $records)
    {
        $fileName = 'pontos-interesse-rurais-'.now()->format('Y-m-d-His').'.csv';
        $path = storage_path('app/exports/');
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
        $filePath = $path.$fileName;

        $data = $records->map(fn ($r) => $this->linha($r));
        SimpleExcelWriter::create($filePath, 'csv')
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToXml(Collection $records)
    {
        $fileName = 'pontos-interesse-rurais-'.now()->format('Y-m-d-His').'.xml';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><pontos_interesse/>');

        foreach ($records as $record) {
            $item = $xml->addChild('ponto');
            foreach ($this->linha($record) as $k => $v) {
                $item->addChild($k, htmlspecialchars((string) $v));
            }
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }
}
