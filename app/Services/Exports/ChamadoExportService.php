<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ChamadoExportService
{
    public function exportToExcel(Collection $chamados)
    {
        $fileName = 'chamados-'.now()->format('Y-m-d-His').'.xlsx';
        $path = storage_path('app/exports/');

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path.$fileName;

        $data = $chamados->map(fn ($c) => $this->linhaCompleta($c));

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $chamados)
    {
        $fileName = 'chamados-'.now()->format('Y-m-d-His').'.pdf';

        $headings = ['Protocolo', 'Categoria', 'Fase', 'Status', 'Solicitante', 'Criado em'];

        $data = $chamados->map(fn ($c) => [
            $c->protocolo,
            $c->categoria?->nome ?? '—',
            $c->faseAtual?->nome ?? '—',
            $c->status,
            $c->solicitante_nome ?? '—',
            $c->created_at?->format('d/m/Y H:i'),
        ]);

        $title = 'Relatório de Chamados';

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }

    /** Colunas completas (Excel) com rótulos amigáveis. */
    private function linhaCompleta($c): array
    {
        return [
            'Protocolo' => $c->protocolo,
            'Categoria' => $c->categoria?->nome ?? '—',
            'Fluxo' => $c->fluxo?->nome ?? '—',
            'Fase Atual' => $c->faseAtual?->nome ?? '—',
            'Status' => $c->status,
            'Solicitante' => $c->solicitante_nome ?? '—',
            'Telefone' => $c->solicitante_telefone ?? '—',
            'E-mail' => $c->solicitante_email ?? '—',
            'Descrição' => $c->descricao ?? '—',
            'Observações' => $c->observacoes ?? '—',
            'Criado em' => $c->created_at?->format('d/m/Y H:i'),
        ];
    }

    /** Chaves ASCII (CSV/XML — sem espaços/acentos para nomes de elementos XML). */
    private function linha($c): array
    {
        return [
            'Protocolo' => $c->protocolo,
            'Categoria' => $c->categoria?->nome ?? '',
            'Fluxo' => $c->fluxo?->nome ?? '',
            'FaseAtual' => $c->faseAtual?->nome ?? '',
            'Status' => $c->status,
            'Solicitante' => $c->solicitante_nome ?? '',
            'Telefone' => $c->solicitante_telefone ?? '',
            'Email' => $c->solicitante_email ?? '',
            'Descricao' => $c->descricao ?? '',
            'Observacoes' => $c->observacoes ?? '',
            'CriadoEm' => $c->created_at?->format('Y-m-d H:i'),
        ];
    }

    public function exportToCsv(Collection $chamados)
    {
        $fileName = 'chamados-'.now()->format('Y-m-d-His').'.csv';
        $path = storage_path('app/exports/');
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
        $filePath = $path.$fileName;

        $data = $chamados->map(fn ($c) => $this->linha($c));
        SimpleExcelWriter::create($filePath, 'csv')
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToXml(Collection $chamados)
    {
        $fileName = 'chamados-'.now()->format('Y-m-d-His').'.xml';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><chamados/>');

        foreach ($chamados as $c) {
            $item = $xml->addChild('chamado');
            foreach ($this->linha($c) as $k => $v) {
                $item->addChild($k, htmlspecialchars((string) $v));
            }
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }
}
