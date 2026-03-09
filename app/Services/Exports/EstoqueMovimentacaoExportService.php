<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class EstoqueMovimentacaoExportService
{
    public function exportToExcel(Collection $movimentacoes)
    {
        $fileName = 'movimentacoes-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $movimentacoes->map(function ($mov) {
            // Concatena os itens (Ex: "5x Lâmpada, 10x Fio")
            $itensStr = $mov->itens->map(fn($i) => $i->quantity . 'x ' . ($i->produto->name ?? ''))->implode(', ');

            return [
                'ID' => $mov->sequential_id,
                'Tipo' => ucfirst($mov->type),
                'Origem' => $mov->origem?->name ?? '-',
                'Destino' => $mov->destino?->name ?? '-',
                'Itens Movimentados' => $itensStr,
                'Operador' => $mov->user?->name ?? 'Sistema',
                'Data' => $mov->created_at->format('d/m/Y H:i'),
                'Motivo/Obs' => $mov->observacao ?? '-',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $movimentacoes)
    {
        $fileName = 'movimentacoes-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Tipo', 'Origem', 'Destino', 'Itens', 'Data'];

        $data = $movimentacoes->map(function ($mov) {
            $itensStr = $mov->itens->map(fn($i) => $i->quantity . 'x ' . ($i->produto->name ?? ''))->implode(', ');
            
            return [
                $mov->sequential_id,
                ucfirst($mov->type),
                $mov->origem?->name ?? '-',
                $mov->destino?->name ?? '-',
                $itensStr,
                $mov->created_at->format('d/m/Y H:i'),
            ];
        });

        $title = 'Relatório de Movimentações de Estoque';

        // SetPaper 'landscape' deixa o PDF deitado para caber a lista de itens tranquilamente
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}