<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class LoteExportService
{
    public function exportToExcel(Collection $lotes)
    {
        $fileName = 'lotes-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $lotes->loadMissing(['unidadesImobiliarias.proprietario', 'edificacoes']);

        $loteData = $lotes->map(function ($lote) {
            return [
                'ID' => $lote->sequential_id,
                'Número do Lote' => $lote->numero_lote,
                'Quadra' => $lote->quadra->name ?? '-',
                'Zona' => $lote->zona->sigla ?? '-',
                'Testada (m)' => $lote->main_facade_length ? number_format($lote->main_facade_length, 2, ',', '') : '0,00',
                'Área Geo (m²)' => $lote->area_geo ? number_format($lote->area_geo, 2, ',', '') : '0,00',
            ];
        });

        $writer = SimpleExcelWriter::create($filePath);
        $writer->nameCurrentSheet('Lotes');
        $writer->addHeader(array_keys($loteData->first() ?? []))->addRows($loteData->toArray());

        $unidadesData = $lotes->flatMap(function ($lote) {
            return $lote->unidadesImobiliarias->map(function ($unidade) use ($lote) {
                return [
                    'Lote' => $lote->numero_lote,
                    'Código Imóvel Tributário' => $unidade->codigo_imovel_tributario ?? '-',
                    'Inscrição Imobiliária' => $unidade->inscricao_imobiliaria ?? '-',
                    'Logradouro' => $unidade->logradouro_nome ?? '-',
                    'Número' => $unidade->numero_imovel ?? '-',
                    'Proprietário' => $unidade->proprietario->name ?? ($unidade->dados_tributarios['proprietario_name'] ?? '-'),
                ];
            });
        });

        if ($unidadesData->isNotEmpty()) {
            $writer->addNewSheetAndMakeItCurrent('Unidades Imobiliárias');
            $writer->addHeader(array_keys($unidadesData->first()))->addRows($unidadesData->toArray());
        }

        $edificacoesData = $lotes->flatMap(function ($lote) {
            return $lote->edificacoes->map(function ($edificacao) use ($lote) {
                return [
                    'Lote' => $lote->numero_lote,
                    'Tipo' => $edificacao->tipo ?? '-',
                    'Tipo de Construção' => $edificacao->tp_construcao ?? '-',
                    'Estado de Conservação' => $edificacao->estado_conservacao ?? '-',
                    'Área (m²)' => $edificacao->area_geo ? number_format($edificacao->area_geo, 2, ',', '') : '0,00',
                ];
            });
        });

        if ($edificacoesData->isNotEmpty()) {
            $writer->addNewSheetAndMakeItCurrent('Edificações');
            $writer->addHeader(array_keys($edificacoesData->first()))->addRows($edificacoesData->toArray());
        }

        $writer->close();

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $lotes)
    {
        $fileName = 'lotes-' . now()->format('Y-m-d-His') . '.pdf';

        $lotes->loadMissing(['unidadesImobiliarias.proprietario', 'edificacoes']);

        $title = 'Relatório de Lotes e Terrenos';

        $pdf = Pdf::loadView('pdf.lote-detalhado-report', compact('lotes', 'title'))->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }

    public function exportToXml(Collection $lotes)
    {
        $fileName = 'lotes-' . now()->format('Y-m-d-His') . '.xml';

        $lotes->loadMissing(['unidadesImobiliarias.proprietario', 'edificacoes']);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><lotes/>');

        foreach ($lotes as $lote) {
            $loteXml = $xml->addChild('lote');
            $loteXml->addAttribute('id', (string) $lote->sequential_id);
            $loteXml->addChild('numero_lote', htmlspecialchars((string) $lote->numero_lote));
            $loteXml->addChild('quadra', htmlspecialchars($lote->quadra->name ?? ''));
            $loteXml->addChild('zona', htmlspecialchars($lote->zona->sigla ?? ''));
            $loteXml->addChild('testada_m', number_format($lote->main_facade_length ?? 0, 2, '.', ''));
            $loteXml->addChild('area_geo_m2', number_format($lote->area_geo ?? 0, 2, '.', ''));

            $unidadesXml = $loteXml->addChild('unidades_imobiliarias');
            foreach ($lote->unidadesImobiliarias as $unidade) {
                $unidadeXml = $unidadesXml->addChild('unidade');
                $unidadeXml->addChild('codigo_imovel_tributario', htmlspecialchars($unidade->codigo_imovel_tributario ?? ''));
                $unidadeXml->addChild('inscricao_imobiliaria', htmlspecialchars($unidade->inscricao_imobiliaria ?? ''));
                $unidadeXml->addChild('logradouro', htmlspecialchars($unidade->logradouro_nome ?? ''));
                $unidadeXml->addChild('numero', htmlspecialchars($unidade->numero_imovel ?? ''));
                $unidadeXml->addChild('proprietario', htmlspecialchars($unidade->proprietario->name ?? ($unidade->dados_tributarios['proprietario_name'] ?? '')));
            }

            $edificacoesXml = $loteXml->addChild('edificacoes');
            foreach ($lote->edificacoes as $edificacao) {
                $edificacaoXml = $edificacoesXml->addChild('edificacao');
                $edificacaoXml->addChild('tipo', htmlspecialchars($edificacao->tipo ?? ''));
                $edificacaoXml->addChild('tp_construcao', htmlspecialchars($edificacao->tp_construcao ?? ''));
                $edificacaoXml->addChild('estado_conservacao', htmlspecialchars($edificacao->estado_conservacao ?? ''));
                $edificacaoXml->addChild('area_geo_m2', number_format($edificacao->area_geo ?? 0, 2, '.', ''));
            }
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }
}
