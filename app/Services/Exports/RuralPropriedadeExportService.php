<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

class RuralPropriedadeExportService
{
    public function exportToExcel(Collection $records)
    {
        $fileName = 'propriedades-rurais-'.now()->format('Y-m-d-His').'.xlsx';
        $path = storage_path('app/exports/');

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path.$fileName;

        $data = $records->map(function ($record) {
            return [
                'ID' => $record->sequential_id,
                'Nome / Fazenda' => $record->nome_propriedade,
                'Localidade' => $record->localidade->nome ?? '-',
                'Proprietário' => $record->proprietario->name ?? '-',
                'INCRA' => $record->codigo_incra ?? '-',
                'CAR' => $record->codigo_car ?? '-',
                'SIGEF' => $record->codigo_sigef ?? '-',
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
        $fileName = 'propriedades-rurais-'.now()->format('Y-m-d-His').'.pdf';

        $headings = ['ID', 'Nome / Fazenda', 'Localidade', 'Proprietário', 'INCRA', 'CAR', 'SIGEF', 'Área Geo (m²)'];

        $data = $records->map(function ($record) {
            return [
                $record->sequential_id,
                $record->nome_propriedade,
                $record->localidade->nome ?? '-',
                $record->proprietario->name ?? '-',
                $record->codigo_incra ?? '-',
                $record->codigo_car ?? '-',
                $record->codigo_sigef ?? '-',
                $record->area_geo ? number_format($record->area_geo, 2, ',', '') : '0,00',
            ];
        });

        $title = 'Relatório de Propriedades Rurais (INCRA/CAR)';

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
            'NomePropriedade' => $record->nome_propriedade,
            'Localidade' => $record->localidade->nome ?? '-',
            'Proprietario' => $record->proprietario->name ?? '-',
            'INCRA' => $record->codigo_incra ?? '-',
            'CAR' => $record->codigo_car ?? '-',
            'SIGEF' => $record->codigo_sigef ?? '-',
            'AreaGeo_m2' => $record->area_geo ? number_format($record->area_geo, 2, '.', '') : '0.00',
        ];
    }

    public function exportToCsv(Collection $records)
    {
        $fileName = 'propriedades-rurais-'.now()->format('Y-m-d-His').'.csv';
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
        $fileName = 'propriedades-rurais-'.now()->format('Y-m-d-His').'.xml';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><propriedades_rurais/>');

        foreach ($records as $record) {
            $item = $xml->addChild('propriedade');
            foreach ($this->linha($record) as $k => $v) {
                $item->addChild($k, htmlspecialchars((string) $v));
            }
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }
}
