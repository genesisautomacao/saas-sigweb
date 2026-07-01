<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class CadastroSocialExportService
{
    public function exportToExcel(Collection $cadastros)
    {
        $fileName = 'cadastros-sociais-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $cadastros->map(function ($cadastro) {
            return [
                'ID' => $cadastro->sequential_id,
                'Responsável Familiar' => $cadastro->responsavel->name ?? '-',
                'NIS' => $cadastro->nis ?? '-',
                'Endereço' => $cadastro->unidadeImobiliaria ? ($cadastro->unidadeImobiliaria->logradouro_nome . ', ' . $cadastro->unidadeImobiliaria->numero_imovel) : 'Sem endereço fixo',
                'Membros' => $cadastro->quantidade_membros,
                'Renda Per Capita' => $cadastro->renda_per_capita ? 'R$ ' . number_format($cadastro->renda_per_capita, 2, ',', '.') : 'R$ 0,00',
                'Benefícios' => $cadastro->recebe_beneficios ? 'Sim' : 'Não',
                'Área de Risco' => $cadastro->em_area_de_risco ? 'Sim' : 'Não',
                'PCD' => $cadastro->possui_membro_com_deficiencia ? 'Sim' : 'Não',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $cadastros)
    {
        $fileName = 'cadastros-sociais-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Responsável', 'Endereço', 'Membros', 'Renda P.C.', 'Benefícios', 'Risco', 'PCD'];

        $data = $cadastros->map(function ($cadastro) {
            return [
                $cadastro->sequential_id,
                $cadastro->responsavel->name ?? '-',
                $cadastro->unidadeImobiliaria ? ($cadastro->unidadeImobiliaria->logradouro_nome . ', ' . $cadastro->unidadeImobiliaria->numero_imovel) : '-',
                $cadastro->quantidade_membros,
                $cadastro->renda_per_capita ? 'R$ ' . number_format($cadastro->renda_per_capita, 2, ',', '.') : 'R$ 0,00',
                $cadastro->recebe_beneficios ? 'Sim' : 'Não',
                $cadastro->em_area_de_risco ? 'Sim' : 'Não',
                $cadastro->possui_membro_com_deficiencia ? 'Sim' : 'Não',
            ];
        });

        $title = 'Relatório de Cadastros Sociais';

        // Utilizando a sua view padrão de PDF que já existe no projeto
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }

    private function linha($c): array
    {
        return [
            'ID'                    => $c->sequential_id,
            'Responsavel'           => $c->responsavel->name ?? '-',
            'NIS'                   => $c->nis ?? '-',
            'Situacao'              => $c->situacao_cadastro ?? '-',
            'Endereco'              => $c->unidadeImobiliaria ? ($c->unidadeImobiliaria->logradouro_nome . ', ' . $c->unidadeImobiliaria->numero_imovel) : 'Sem endereco fixo',
            'Membros'               => $c->quantidade_membros,
            'RendaFamiliar'         => (float) $c->renda_familiar_total,
            'RendaPerCapita'        => (float) $c->renda_per_capita,
            'IndiceVulnerabilidade' => $c->indice_vulnerabilidade,
            'Beneficios'            => $c->recebe_beneficios ? 'Sim' : 'Nao',
            'AreaRisco'             => $c->em_area_de_risco ? 'Sim' : 'Nao',
            'PCD'                   => $c->possui_membro_com_deficiencia ? 'Sim' : 'Nao',
        ];
    }

    public function exportToCsv(Collection $cadastros)
    {
        $fileName = 'cadastros-sociais-' . now()->format('Y-m-d-His') . '.csv';
        $path = storage_path('app/exports/');
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
        $filePath = $path . $fileName;

        $data = $cadastros->map(fn($c) => $this->linha($c));
        SimpleExcelWriter::create($filePath, 'csv')
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToXml(Collection $cadastros)
    {
        $fileName = 'cadastros-sociais-' . now()->format('Y-m-d-His') . '.xml';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><familias/>');

        foreach ($cadastros as $c) {
            $item = $xml->addChild('familia');
            $item->addAttribute('id', (string) $c->sequential_id);
            foreach ($this->linha($c) as $k => $v) {
                $item->addChild(lcfirst($k), htmlspecialchars((string) $v));
            }
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }
}