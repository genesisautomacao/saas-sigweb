<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class PosteExportService
{
    public function exportToExcel(Collection $postes)
    {
        $fileName = 'postes-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $postes->map(function ($poste) {
            return [
                'ID' => $poste->sequential_id,
                'Referência/Endereço' => $poste->address ?? 'S/N',
                'Tipo' => $poste->tipoPoste?->name ?? 'Não Definido',
                'Luminária' => $poste->luminaire_type ?? '-',
                'Potência' => $poste->lamp_power ?? '-',
                'Condição' => $poste->structural_condition ?? '-',
                'Data Instalação' => $poste->installation_date ? $poste->installation_date->format('d/m/Y') : '-',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $postes)
    {
        $fileName = 'postes-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Endereço', 'Tipo', 'Luminária', 'Potência', 'Condição'];

        $data = $postes->map(function ($poste) {
            return [
                $poste->sequential_id,
                $poste->address ?? 'S/N',
                $poste->tipoPoste?->name ?? '-',
                $poste->luminaire_type ?? '-',
                $poste->lamp_power ?? '-',
                $poste->structural_condition ?? '-',
            ];
        });

        $title = 'Relatório de Iluminação Pública (Postes)';

        // Usa a view global de PDF
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}