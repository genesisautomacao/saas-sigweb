<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class LogradouroCemiterioExportService
{
    public function exportToExcel(Collection $logradouros)
    {
        $fileName = 'ruas_cemiterio-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $logradouros->map(function ($logradouro) {
            return [
                'ID' => $logradouro->sequential_id,
                'Cemitério' => $logradouro->cemiterio->name ?? 'Não Informado',
                'Nome da Rua/Viela' => $logradouro->name ?? 'S/N',
                'Criado em' => $logradouro->created_at ? $logradouro->created_at->format('d/m/Y') : '-',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $logradouros)
    {
        $fileName = 'ruas_cemiterio-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Cemitério', 'Nome da Rua/Viela', 'Cadastro'];

        $data = $logradouros->map(function ($logradouro) {
            return [
                $logradouro->sequential_id,
                $logradouro->cemiterio->name ?? 'Não Informado',
                $logradouro->name ?? 'S/N',
                $logradouro->created_at ? $logradouro->created_at->format('d/m/Y') : '-',
            ];
        });

        $title = 'Relatório de Ruas e Vielas de Cemitério';

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}