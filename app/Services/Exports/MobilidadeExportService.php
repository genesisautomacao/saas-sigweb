<?php

namespace App\Services\Exports;

use App\Models\MobCamera;
use App\Models\MobEixo;
use App\Models\MobFluxo;
use App\Models\MobPontoInteresse;
use App\Models\MobTrecho;
use App\Models\MobZona;
use App\Services\Coleta\CampoCustomizadoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Exports do módulo Mobilidade Urbana (docs/piuma.txt, Onda 3) — XLS/PDF/CSV/XML.
 *
 * PARAMETRIZADO por entidade (desvio consciente do padrão 1-service-por-entidade:
 * são 6 entidades com o MESMO esqueleto; a spec de colunas por entidade vive em
 * spec()). Excel/CSV levam também os campos do kit (colunasExport) em
 * trecho/eixo; PDF usa a blade global pdf.default-report.
 */
class MobilidadeExportService
{
    /** ['titulo', 'arquivo', 'xml_raiz', 'xml_item', 'colunas' => [Label => fn($r)]] */
    protected function spec(string $entidade): array
    {
        return match ($entidade) {
            'mob_trecho' => [
                'titulo' => 'Trechos Viários — Mobilidade Urbana',
                'arquivo' => 'mob-trechos',
                'xml_raiz' => 'trechos_viarios',
                'xml_item' => 'trecho',
                'custom' => 'mob_trecho',
                'colunas' => [
                    'ID' => fn (MobTrecho $r) => $r->sequential_id,
                    'Sentido' => fn (MobTrecho $r) => $r->sentido ? (MobTrecho::SENTIDOS[$r->sentido] ?? $r->sentido) : 'Não classificado',
                    'Azimute (°)' => fn (MobTrecho $r) => $r->azimute !== null ? number_format((float) $r->azimute, 1, ',', '.') : '-',
                    'Extensão (m)' => fn (MobTrecho $r) => $r->extensao_geo !== null ? number_format((float) $r->extensao_geo, 2, ',', '.') : '-',
                    'Tipologia da via' => fn (MobTrecho $r) => $r->tipologia_da_via ?? '-',
                    'Pavimentação' => fn (MobTrecho $r) => $r->tipo_de_pavimentacao ?? '-',
                    'Estado da pavimentação' => fn (MobTrecho $r) => $r->estado_conservacao_pavimentacao ?? '-',
                    'Classe da faixa' => fn (MobTrecho $r) => $r->classe_faixa_rodagem ?? '-',
                    'Largura da via' => fn (MobTrecho $r) => $r->dimensionamento_da_via ?? '-',
                    'Logradouro' => fn (MobTrecho $r) => $r->logradouro?->name ?? '-',
                ],
            ],
            'mob_sinalizacao' => [
                'titulo' => 'Sinalização Viária — Mobilidade Urbana',
                'arquivo' => 'mob-sinalizacoes',
                'xml_raiz' => 'sinalizacoes',
                'xml_item' => 'sinalizacao',
                'colunas' => [
                    'ID' => fn ($r) => $r->sequential_id,
                    'Tipo (catálogo)' => fn ($r) => $r->tipoSinalizacao?->name ?? 'A Classificar',
                    'Vertical/Horizontal' => fn ($r) => ucfirst($r->tipoSinalizacao?->tipo ?? '-'),
                    'Código CTB' => fn ($r) => $r->tipoSinalizacao?->codigo_ctb ?? '-',
                    'Texto original da coleta' => fn ($r) => $r->descricao_original ?? '-',
                    'Observação' => fn ($r) => $r->observacao ?? '-',
                ],
            ],
            'mob_ponto_interesse' => [
                'titulo' => 'Pontos de Interesse — Mobilidade Urbana',
                'arquivo' => 'mob-pontos-interesse',
                'xml_raiz' => 'pontos_interesse',
                'xml_item' => 'ponto',
                'colunas' => [
                    'ID' => fn ($r) => $r->sequential_id,
                    'Categoria' => fn ($r) => MobPontoInteresse::CATEGORIAS[$r->categoria] ?? ($r->categoria ?? '-'),
                    'Nome' => fn ($r) => $r->name ?? '-',
                    'Número' => fn ($r) => $r->numero ?? '-',
                ],
            ],
            'mob_camera' => [
                'titulo' => 'Monitoramento em Tempo Real — Câmeras',
                'arquivo' => 'mob-cameras',
                'xml_raiz' => 'cameras',
                'xml_item' => 'camera',
                'colunas' => [
                    'ID' => fn (MobCamera $r) => $r->sequential_id,
                    'Nome / local' => fn (MobCamera $r) => $r->nome ?? '-',
                    'Fonte' => fn (MobCamera $r) => MobCamera::TIPOS[$r->tipo] ?? ($r->tipo ?? '-'),
                    'Provedor' => fn (MobCamera $r) => $r->provedor ?? '-',
                    'Ativa' => fn (MobCamera $r) => $r->ativo ? 'Sim' : 'Não',
                    'Azimute da visada (graus)' => fn (MobCamera $r) => $r->azimute_visada !== null ? number_format((float) $r->azimute_visada, 0) : '-',
                    'URL' => fn (MobCamera $r) => $r->url ?? '-',
                ],
            ],
            'mob_eixo' => [
                'titulo' => 'Eixos de Mobilidade',
                'arquivo' => 'mob-eixos',
                'xml_raiz' => 'eixos',
                'xml_item' => 'eixo',
                'custom' => 'mob_eixo',
                'colunas' => [
                    'ID' => fn ($r) => $r->sequential_id,
                    'Tipo' => fn ($r) => MobEixo::TIPOS[$r->tipo] ?? ($r->tipo ?? '-'),
                    'Nome' => fn ($r) => $r->name ?? '-',
                    'Extensão (km)' => fn ($r) => $r->extensao_geo !== null ? number_format((float) $r->extensao_geo / 1000, 2, ',', '.') : '-',
                ],
            ],
            'mob_zona' => [
                'titulo' => 'Zonas de Estudo — Mobilidade Urbana',
                'arquivo' => 'mob-zonas',
                'xml_raiz' => 'zonas_estudo',
                'xml_item' => 'zona',
                'colunas' => [
                    'ID' => fn ($r) => $r->sequential_id,
                    'Tipo' => fn ($r) => MobZona::TIPOS[$r->tipo] ?? ($r->tipo ?? '-'),
                    'Nome' => fn ($r) => $r->name ?? '-',
                    'Código (IBGE)' => fn ($r) => $r->codigo ?? '-',
                    'Situação' => fn ($r) => $r->situacao ?? '-',
                    '% Origens' => fn ($r) => $r->origens !== null ? number_format((float) $r->origens, 2, ',', '.') : '-',
                    '% Destinos' => fn ($r) => $r->destinos !== null ? number_format((float) $r->destinos, 2, ',', '.') : '-',
                    'Área (m²)' => fn ($r) => $r->area_geo !== null ? number_format((float) $r->area_geo, 2, ',', '.') : '-',
                ],
            ],
            'mob_fluxo' => [
                'titulo' => 'Fluxos Origem/Destino — Mobilidade Urbana',
                'arquivo' => 'mob-fluxos',
                'xml_raiz' => 'fluxos_od',
                'xml_item' => 'fluxo',
                'colunas' => [
                    'ID' => fn ($r) => $r->sequential_id,
                    'Destino' => fn ($r) => MobFluxo::DESTINOS[$r->destino] ?? ($r->destino ?? '-'),
                    'Volume de deslocamentos' => fn ($r) => (int) $r->valores,
                    'Intrazonal (sem linha no mapa)' => fn ($r) => ($r->intrazonal ?? false) ? 'Sim' : 'Não',
                ],
            ],
        };
    }

    /** Linha completa (Excel/CSV): colunas da spec + campos do kit quando houver. */
    protected function linha(array $spec, $registro): array
    {
        $linha = [];
        foreach ($spec['colunas'] as $label => $fn) {
            $linha[$label] = $fn($registro);
        }
        if (isset($spec['custom'])) {
            $linha += CampoCustomizadoService::colunasExport(
                $spec['custom'],
                $registro->dados_customizados,
                $registro->tenant_id,
            );
        }

        return $linha;
    }

    protected function caminho(string $nome): string
    {
        $path = storage_path('app/exports/');
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        return $path.$nome;
    }

    public function exportToExcel(string $entidade, Collection $registros)
    {
        $spec = $this->spec($entidade);
        $filePath = $this->caminho($spec['arquivo'].'-'.now()->format('Y-m-d-His').'.xlsx');
        $data = $registros->map(fn ($r) => $this->linha($spec, $r));

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToCsv(string $entidade, Collection $registros)
    {
        $spec = $this->spec($entidade);
        $filePath = $this->caminho($spec['arquivo'].'-'.now()->format('Y-m-d-His').'.csv');
        $data = $registros->map(fn ($r) => $this->linha($spec, $r));

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(string $entidade, Collection $registros)
    {
        $spec = $this->spec($entidade);
        $headings = array_keys($spec['colunas']);
        $data = $registros->map(fn ($r) => array_values(array_map(fn ($fn) => $fn($r), $spec['colunas'])));
        $title = $spec['titulo'];

        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));

        return response()->streamDownload(
            fn () => print ($pdf->stream()),
            $spec['arquivo'].'-'.now()->format('Y-m-d-His').'.pdf'
        );
    }

    public function exportToXml(string $entidade, Collection $registros)
    {
        $spec = $this->spec($entidade);
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><'.$spec['xml_raiz'].'/>');

        foreach ($registros as $registro) {
            $item = $xml->addChild($spec['xml_item']);
            foreach ($this->linha($spec, $registro) as $label => $valor) {
                $tag = \Illuminate\Support\Str::slug($label, '_') ?: 'campo';
                if (preg_match('/^\d/', $tag)) {
                    $tag = 'c_'.$tag;
                }
                $item->addChild($tag, htmlspecialchars((string) $valor, ENT_XML1, 'UTF-8'));
            }
        }

        return response()->streamDownload(
            fn () => print ($xml->asXML()),
            $spec['arquivo'].'-'.now()->format('Y-m-d-His').'.xml',
            ['Content-Type' => 'application/xml']
        );
    }
}
