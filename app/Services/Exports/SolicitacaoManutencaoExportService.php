<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class SolicitacaoManutencaoExportService
{
    public function exportToExcel(Collection $solicitacoes)
    {
        $fileName = 'solicitacoes-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $solicitacoes->map(function ($sol) {
            $artefato = $sol->asset_type ? class_basename($sol->asset_type) . ' #' . ($sol->asset->sequential_id ?? '?') : '-';

            return [
                'ID' => $sol->sequential_id,
                'Artefato' => $artefato,
                'Serviço' => $sol->tipo_servico,
                'Prioridade' => ucfirst($sol->prioridade),
                'Status' => strtoupper($sol->status),
                'Reclamante (Cadastro)' => $sol->pessoa?->name ?? '-',
                'Reclamante (Avulso)' => $sol->solicitante_nome ?? '-',
                'Abertura' => $sol->created_at->format('d/m/Y H:i'),
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $solicitacoes)
    {
        $fileName = 'solicitacoes-' . now()->format('Y-m-d-His') . '.pdf';

        $headings = ['ID', 'Artefato', 'Serviço', 'Prioridade', 'Status', 'Reclamante', 'Abertura'];

        $data = $solicitacoes->map(function ($sol) {
            $artefato = $sol->asset_type ? class_basename($sol->asset_type) . ' #' . ($sol->asset->sequential_id ?? '?') : '-';
            $reclamante = $sol->pessoa?->name ?? $sol->solicitante_nome ?? 'Não Informado';

            return [
                $sol->sequential_id,
                $artefato,
                $sol->tipo_servico,
                ucfirst($sol->prioridade),
                strtoupper($sol->status),
                $reclamante,
                $sol->created_at->format('d/m/Y'),
            ];
        });

        $title = 'Relatório de Solicitações de Manutenção';

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }
}