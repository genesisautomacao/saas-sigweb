<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

class SolicitacaoManutencaoExportService
{
    public function exportToExcel(Collection $solicitacoes)
    {
        $fileName = 'solicitacoes-'.now()->format('Y-m-d-His').'.xlsx';
        $path = storage_path('app/exports/');

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path.$fileName;

        $data = $solicitacoes->map(function ($sol) {
            $artefato = $sol->asset_type ? class_basename($sol->asset_type).' #'.($sol->asset->sequential_id ?? '?') : '-';

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
        $fileName = 'solicitacoes-'.now()->format('Y-m-d-His').'.pdf';

        $headings = ['ID', 'Artefato', 'Serviço', 'Prioridade', 'Status', 'Reclamante', 'Abertura'];

        $data = $solicitacoes->map(function ($sol) {
            $artefato = $sol->asset_type ? class_basename($sol->asset_type).' #'.($sol->asset->sequential_id ?? '?') : '-';
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

    private function linha($sol): array
    {
        $artefato = $sol->asset_type ? class_basename($sol->asset_type).' #'.($sol->asset->sequential_id ?? '?') : '-';

        return [
            'ID' => $sol->sequential_id,
            'Artefato' => $artefato,
            'Servico' => $sol->tipo_servico,
            'Prioridade' => ucfirst($sol->prioridade),
            'Status' => strtoupper($sol->status),
            'ReclamanteCadastro' => $sol->pessoa?->name ?? '-',
            'ReclamanteAvulso' => $sol->solicitante_nome ?? '-',
            'Abertura' => $sol->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function exportToCsv(Collection $solicitacoes)
    {
        $fileName = 'solicitacoes-'.now()->format('Y-m-d-His').'.csv';
        $path = storage_path('app/exports/');
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
        $filePath = $path.$fileName;

        $data = $solicitacoes->map(fn ($s) => $this->linha($s));
        SimpleExcelWriter::create($filePath, 'csv')
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToXml(Collection $solicitacoes)
    {
        $fileName = 'solicitacoes-'.now()->format('Y-m-d-His').'.xml';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><solicitacoes/>');

        foreach ($solicitacoes as $sol) {
            $item = $xml->addChild('solicitacao');
            foreach ($this->linha($sol) as $k => $v) {
                $item->addChild($k, htmlspecialchars((string) $v));
            }
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }
}
