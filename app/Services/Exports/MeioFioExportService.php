<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

class MeioFioExportService
{
    public function exportToExcel(Collection $records)
    {
        $fileName = 'meios-fio-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $data = $records->map(fn ($r) => [
            'ID'              => $r->sequential_id,
            'Logradouro'      => $r->logradouro?->name ?? '-',
            'Material'        => $r->material ? ucfirst($r->material) : '-',
            'Conservação'     => $r->estado_conservacao ? ucfirst($r->estado_conservacao) : '-',
            'Extensão (m)'    => $r->extensao_geo ? number_format((float) $r->extensao_geo, 2, ',', '.') : '0,00',
            'Observações'     => $r->observacoes ?? '',
        ]);

        SimpleExcelWriter::create($path . $fileName)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($path . $fileName)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $records)
    {
        $fileName = 'meios-fio-' . now()->format('Y-m-d-His') . '.pdf';
        $data = $records->map(fn ($r) => [
            $r->sequential_id,
            $r->logradouro?->name ?? '-',
            $r->material ? ucfirst($r->material) : '-',
            $r->estado_conservacao ? ucfirst($r->estado_conservacao) : '-',
            $r->extensao_geo ? number_format((float) $r->extensao_geo, 2, ',', '.') . ' m' : '-',
        ]);
        $headings = ['ID', 'Logradouro', 'Material', 'Conservação', 'Extensão'];

        $pdf = Pdf::loadView('pdf.default-report', [
            'data'     => $data,
            'headings' => $headings,
            'title'    => 'Relatório de Meios-fio / Calçadas',
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(fn () => print ($pdf->output()), $fileName);
    }
}
