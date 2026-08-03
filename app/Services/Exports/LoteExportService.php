<?php

namespace App\Services\Exports;

use App\Services\Coleta\CampoCustomizadoService;
use App\Services\Coleta\CampoDominioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

class LoteExportService
{
    public function exportToExcel(Collection $lotes)
    {
        $fileName = 'lotes-'.now()->format('Y-m-d-His').'.xlsx';
        $path = storage_path('app/exports/');

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path.$fileName;

        $lotes->loadMissing(['unidadesImobiliarias.proprietario', 'edificacoes']);

        $loteData = $lotes->map(function ($lote) {
            return array_merge([
                'ID' => $lote->sequential_id,
                'Número do Lote' => $lote->numero_lote,
                'Quadra' => $lote->quadra->name ?? '-',
                'Zona' => $lote->zona->sigla ?? '-',
                'Testada (m)' => $lote->main_facade_length ? number_format($lote->main_facade_length, 2, ',', '') : '0,00',
                'Área Geo (m²)' => $lote->area_geo ? number_format($lote->area_geo, 2, ',', '') : '0,00',
                // R67-2 — rótulo do campo E do valor definidos pelo município.
                // situacao_quadra e demais atributos viraram campos customizados (abaixo).
                CampoDominioService::label('lote', 'ocupacao') => CampoDominioService::rotuloValor('lote', 'ocupacao', $lote->ocupacao) ?? '-',
            ], CampoCustomizadoService::colunasExport('lote', $lote->dados_customizados)); // R67-1
        });

        $writer = SimpleExcelWriter::create($filePath);
        $writer->nameCurrentSheet('Lotes');
        $writer->addHeader(array_keys($loteData->first() ?? []))->addRows($loteData->toArray());

        $unidadesData = $lotes->flatMap(function ($lote) {
            return $lote->unidadesImobiliarias->map(function ($unidade) use ($lote) {
                return array_merge([
                    'Lote' => $lote->numero_lote,
                    'Código Imóvel Tributário' => $unidade->codigo_imovel_tributario ?? '-',
                    'Inscrição Imobiliária' => $unidade->inscricao_imobiliaria ?? '-',
                    'Logradouro' => $unidade->logradouro_nome ?? '-',
                    'Número' => $unidade->numero_imovel ?? '-',
                    'Proprietário' => $unidade->proprietario->name ?? ($unidade->dados_tributarios['proprietario_name'] ?? '-'),
                ], CampoCustomizadoService::colunasExport('unidade', $unidade->dados_customizados)); // R67-1
            });
        });

        if ($unidadesData->isNotEmpty()) {
            $writer->addNewSheetAndMakeItCurrent('Unidades Imobiliárias');
            $writer->addHeader(array_keys($unidadesData->first()))->addRows($unidadesData->toArray());
        }

        // Refatoração PoC Tangará: os atributos descritivos da edificação são campos
        // customizados — colunasExport() já os rotula e formata.
        $edificacoesData = $lotes->flatMap(function ($lote) {
            return $lote->edificacoes->map(function ($edificacao) use ($lote) {
                return array_merge([
                    'Lote' => $lote->numero_lote,
                    'Área (m²)' => $edificacao->area_geo ? number_format($edificacao->area_geo, 2, ',', '') : '0,00',
                ], CampoCustomizadoService::colunasExport('edificacao', $edificacao->dados_customizados)); // R67-1
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
        $fileName = 'lotes-'.now()->format('Y-m-d-His').'.pdf';

        $lotes->loadMissing(['unidadesImobiliarias.proprietario', 'edificacoes']);

        $title = 'Relatório de Lotes e Terrenos';

        // R67-1/2 — campos do município (definições + rótulos personalizados)
        $camposCustom = [
            'lote' => CampoCustomizadoService::definicoes('lote'),
            'unidade' => CampoCustomizadoService::definicoes('unidade'),
            'edificacao' => CampoCustomizadoService::definicoes('edificacao'),
        ];
        $rotulos = [
            'lote' => CampoDominioService::rotulos('lote'),
            'edificacao' => CampoDominioService::rotulos('edificacao'),
        ];

        $pdf = Pdf::loadView('pdf.lote-detalhado-report', compact('lotes', 'title', 'camposCustom', 'rotulos'))->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }

    public function exportToXml(Collection $lotes)
    {
        $fileName = 'lotes-'.now()->format('Y-m-d-His').'.xml';

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
            $loteXml->addChild('ocupacao', htmlspecialchars((string) ($lote->ocupacao ?? '')));
            self::xmlCustomizados($loteXml, 'lote', $lote->dados_customizados); // R67-1 (inclui situacao_quadra)

            $unidadesXml = $loteXml->addChild('unidades_imobiliarias');
            foreach ($lote->unidadesImobiliarias as $unidade) {
                $unidadeXml = $unidadesXml->addChild('unidade');
                $unidadeXml->addChild('codigo_imovel_tributario', htmlspecialchars($unidade->codigo_imovel_tributario ?? ''));
                $unidadeXml->addChild('inscricao_imobiliaria', htmlspecialchars($unidade->inscricao_imobiliaria ?? ''));
                $unidadeXml->addChild('logradouro', htmlspecialchars($unidade->logradouro_nome ?? ''));
                $unidadeXml->addChild('numero', htmlspecialchars($unidade->numero_imovel ?? ''));
                $unidadeXml->addChild('proprietario', htmlspecialchars($unidade->proprietario->name ?? ($unidade->dados_tributarios['proprietario_name'] ?? '')));
                self::xmlCustomizados($unidadeXml, 'unidade', $unidade->dados_customizados); // R67-1
            }

            $edificacoesXml = $loteXml->addChild('edificacoes');
            foreach ($lote->edificacoes as $edificacao) {
                $edificacaoXml = $edificacoesXml->addChild('edificacao');
                $edificacaoXml->addChild('area_geo_m2', number_format($edificacao->area_geo ?? 0, 2, '.', ''));
                // Atributos descritivos = campos customizados (tipo_edificacao, pavimento...)
                self::xmlCustomizados($edificacaoXml, 'edificacao', $edificacao->dados_customizados); // R67-1
            }
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }

    /** R67-1 — bloco <dados_customizados> com os campos do município. */
    protected static function xmlCustomizados(\SimpleXMLElement $pai, string $entidade, ?array $dados): void
    {
        $definicoes = CampoCustomizadoService::definicoes($entidade);

        if ($definicoes->isEmpty()) {
            return;
        }

        $bloco = $pai->addChild('dados_customizados');

        foreach ($definicoes as $campo) {
            $valor = $dados[$campo->slug] ?? null;
            $valor = is_array($valor) ? implode(', ', $valor) : (is_bool($valor) ? ($valor ? 'Sim' : 'Não') : (string) $valor);

            $item = $bloco->addChild('campo', htmlspecialchars($valor));
            $item->addAttribute('slug', $campo->slug);
            $item->addAttribute('label', $campo->label);
        }
    }

    public function exportToCsv(Collection $lotes)
    {
        $fileName = 'lotes-'.now()->format('Y-m-d-His').'.csv';
        $path = storage_path('app/exports/');
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
        $filePath = $path.$fileName;

        // CSV é plano: os campos principais do lote (unidades/edificações continuam no Excel/XML/PDF).
        $data = $lotes->map(fn ($lote) => [
            'ID' => $lote->sequential_id,
            'NumeroLote' => $lote->numero_lote,
            'Quadra' => $lote->quadra->name ?? '-',
            'Zona' => $lote->zona->sigla ?? '-',
            'Testada_m' => $lote->main_facade_length ? number_format($lote->main_facade_length, 2, '.', '') : '0.00',
            'AreaGeo_m2' => $lote->area_geo ? number_format($lote->area_geo, 2, '.', '') : '0.00',
        ]);
        SimpleExcelWriter::create($filePath, 'csv')
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }
}
