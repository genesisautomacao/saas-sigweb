<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class UserExportService
{
    public function exportToExcel(Collection $users)
    {
        $fileName = 'usuarios-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $users->map(function ($user) {
            return [
                'Nome' => $user->name,
                'Email' => $user->email,
                'Papel' => $user->roles->first()?->name ?? 'Sem Papel',
                'Criado em' => $user->created_at->format('d/m/Y H:i'),
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $users)
    {
        $fileName = 'usuarios-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['Nome', 'Email', 'Papel', 'Criado em'];

        $data = $users->map(function ($user) {
            return [
                $user->name,
                $user->email,
                $user->roles->first()?->name ?? 'Sem Papel',
                $user->created_at->format('d/m/Y H:i'),
            ];
        });

        $title = 'Relatório de Usuários da Equipe';

        // Usa a view global que criamos no Passo 2
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        // Retorna o download direto sem precisar salvar no disco
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}