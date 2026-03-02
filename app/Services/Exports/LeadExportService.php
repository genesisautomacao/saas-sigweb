<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class LeadExportService
{
    public function exportToExcel(Collection $users)
    {
        $fileName = 'leads-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $users->map(function ($lead) {
            return [
                'Nome' => $lead->name,
                'Email' => $lead->email,
                'Telefone' => $lead->phone,
                'Status' => $lead->status?->name ?? 'Sem Status',
                'Vendedor' => $lead->seller?->user->name ?? 'Sem Vendedor',
                'Data de Retorno' => $lead->last_follow_up_date?->format('d/m/Y') ?? 'Não agendado',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $users)
    {
        $fileName = 'leads-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['Nome', 'Email', 'Telefone', 'Status', 'Vendedor', 'Data do Retorno'];

        $data = $users->map(function ($lead) {
            return [
                $lead->name,
                $lead->email,
                $lead->phone,
                $lead->status?->name ?? 'Sem Status',
                $lead->seller?->user->name ?? 'Sem Vendedor',
                $lead->last_follow_up_date?->format('d/m/Y') ?? 'Não agendado',
            ];
        });

        $title = 'Relatório de Leads';

        // Usa a view global que criamos no Passo 2
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        // Retorna o download direto sem precisar salvar no disco
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}