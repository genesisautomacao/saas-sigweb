<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

/** B9 — Setores Cadastrais (inscrição): Excel/PDF/CSV/XML com os bairros de cada setor. */
class SetorCadastralExportService
{
    private function linha($r): array
    {
        return [
            'ID' => $r->sequential_id,
            'Setor' => $r->name,
            'Bairros' => $r->bairros->sortBy('name')->pluck('name')->join(', '),
            'Área (m²)' => $r->area_geo !== null ? number_format((float) $r->area_geo, 2, ',', '.') : '-',
        ];
    }

    private function caminho(string $ext): string
    {
        $path = storage_path('app/exports/');
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        return $path.'setores-cadastrais-'.now()->format('Y-m-d-His').'.'.$ext;
    }

    public function exportToExcel(Collection $records)
    {
        $arquivo = $this->caminho('xlsx');
        $data = $records->map(fn ($r) => $this->linha($r));
        SimpleExcelWriter::create($arquivo)->addHeader(array_keys($data->first() ?? $this->linha(new \App\Models\SetorCadastral)))->addRows($data->toArray());

        return response()->download($arquivo)->deleteFileAfterSend(true);
    }

    public function exportToCsv(Collection $records)
    {
        $arquivo = $this->caminho('csv');
        $data = $records->map(fn ($r) => $this->linha($r));
        SimpleExcelWriter::create($arquivo, 'csv')->addHeader(array_keys($data->first() ?? $this->linha(new \App\Models\SetorCadastral)))->addRows($data->toArray());

        return response()->download($arquivo)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $records)
    {
        $data = $records->map(fn ($r) => array_values($this->linha($r)));
        $pdf = Pdf::loadView('pdf.default-report', [
            'data' => $data,
            'headings' => ['ID', 'Setor', 'Bairros', 'Área (m²)'],
            'title' => 'Relatório de Setores Cadastrais',
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(fn () => print ($pdf->output()), 'setores-cadastrais-'.now()->format('Y-m-d-His').'.pdf');
    }

    public function exportToXml(Collection $records)
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><setores_cadastrais/>');

        foreach ($records as $r) {
            $item = $xml->addChild('setor_cadastral');
            $item->addAttribute('id', (string) $r->sequential_id);
            $item->addChild('setor', htmlspecialchars($r->name ?? ''));
            $item->addChild('area_m2', (string) ($r->area_geo ?? ''));
            $bairros = $item->addChild('bairros');
            foreach ($r->bairros->sortBy('name') as $b) {
                $bairros->addChild('bairro', htmlspecialchars($b->name ?? ''))->addAttribute('id', (string) $b->sequential_id);
            }
        }

        return response()->streamDownload(fn () => print $xml->asXML(),
            'setores-cadastrais-'.now()->format('Y-m-d-His').'.xml', ['Content-Type' => 'application/xml']);
    }
}
