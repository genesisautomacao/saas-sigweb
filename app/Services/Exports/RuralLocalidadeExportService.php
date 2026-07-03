<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

class RuralLocalidadeExportService
{
    public function exportToExcel(Collection $records)
    {
        $fileName = 'localidades-rurais-'.now()->format('Y-m-d-His').'.xlsx';
        $path = storage_path('app/exports/');

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path.$fileName;

        $data = $records->map(function ($record) {
            return [
                'ID' => $record->sequential_id,
                'Nome' => $record->nome,
                'Tipo' => $record->tipo,
                'Área Geo (m²)' => $record->area_geo ? number_format($record->area_geo, 2, ',', '') : '0,00',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $records)
    {
        $fileName = 'localidades-rurais-'.now()->format('Y-m-d-His').'.pdf';

        $headings = ['ID', 'Nome', 'Tipo', 'Área Geo (m²)'];

        $data = $records->map(function ($record) {
            return [
                $record->sequential_id,
                $record->nome,
                $record->tipo,
                $record->area_geo ? number_format($record->area_geo, 2, ',', '') : '0,00',
            ];
        });

        $title = 'Relatório de Localidades e Distritos Rurais';

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }

    private function linha($record): array
    {
        return [
            'ID' => $record->sequential_id,
            'Nome' => $record->nome,
            'Tipo' => $record->tipo,
            'AreaGeo_m2' => $record->area_geo ? number_format($record->area_geo, 2, '.', '') : '0.00',
        ];
    }

    public function exportToCsv(Collection $records)
    {
        $fileName = 'localidades-rurais-'.now()->format('Y-m-d-His').'.csv';
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
        $fileName = 'localidades-rurais-'.now()->format('Y-m-d-His').'.xml';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><localidades/>');

        foreach ($records as $record) {
            $item = $xml->addChild('localidade');
            foreach ($this->linha($record) as $k => $v) {
                $item->addChild($k, htmlspecialchars((string) $v));
            }
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }
}
