<?php
namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class LoteamentoExportService
{
    public function exportToExcel(Collection $records)
    {
        $fileName = 'loteamentos-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');
        if (!File::isDirectory($path)) File::makeDirectory($path, 0755, true, true);

        $data = $records->map(fn($r) => ['ID' => $r->sequential_id, 'Código' => $r->code, 'Nome' => $r->name, 'Setor' => $r->setor ?? '-']);

        SimpleExcelWriter::create($path . $fileName)->addHeader(array_keys($data->first() ?? []))->addRows($data->toArray());
        return response()->download($path . $fileName)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $records)
    {
        $fileName = 'loteamentos-' . now()->format('Y-m-d-His') . '.pdf';
        $data = $records->map(fn($r) => [$r->sequential_id, $r->code, $r->name, $r->setor ?? '-']);
        $headings = ['ID', 'Código', 'Nome', 'Setor'];

        $pdf = Pdf::loadView('pdf.default-report', ['data' => $data, 'headings' => $headings, 'title' => 'Relatório de Loteamentos'])->setPaper('a4', 'portrait');
        return response()->streamDownload(fn() => print($pdf->output()), $fileName);
    }
}