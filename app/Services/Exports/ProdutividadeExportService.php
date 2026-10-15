<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ProdutividadeExportService
{
    public function exportToExcel(Collection $lotes)
    {
        $fileName = 'coletas-produtividade-'.now()->format('Y-m-d-His').'.xlsx';
        $path = storage_path('app/exports/');

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path.$fileName;

        $statusLabels = [
            'nao_visitado' => 'Não Visitado',
            'coletado' => 'Coletado',
            'pendente' => 'Pendente',
            'inconformidade' => 'Inconformidade',
        ];

        // R67-2 — ocupação e situação seguem o vocabulário do município
        $dominio = \App\Services\Coleta\CampoDominioService::class;

        // Refatoração PoC Tangará: coleta (quem/quando/observação/inconformidade) vive em
        // coleta_imobiliaria; situacao_quadra é campo customizado (dados_customizados).
        $data = $lotes->map(function ($lote) use ($statusLabels, $dominio) {
            $coleta = $lote->coletaVigente;
            $custom = (array) $lote->dados_customizados;

            return [
                'ID' => $lote->sequential_id,
                'Lote nº' => $lote->numero_lote ?? '—',
                'Quadra' => $lote->quadra->name ?? '—',
                'Zona' => $lote->zona->sigla ?? '—',
                'Status' => $statusLabels[$lote->status_cadastro] ?? '—',
                $dominio::label('lote', 'ocupacao') => $dominio::rotuloValor('lote', 'ocupacao', $lote->ocupacao) ?? '—',
                'Situação na Quadra' => $custom['situacao_quadra'] ?? '—',
                'Cadastrador' => $coleta?->coletadoPor?->name ?? '—',
                'Data Coleta' => $coleta?->coletado_em ? $coleta->coletado_em->format('d/m/Y H:i') : '—',
                'Área Lote (m²)' => $lote->area_geo ? number_format($lote->area_geo, 2, ',', '') : '0,00',
                'Testada (m)' => $lote->main_facade_length ? number_format($lote->main_facade_length, 2, ',', '') : '0,00',
                'Observação' => $coleta?->observacao ?? '',
                'Total Unidades' => $lote->unidadesImobiliarias->count(),
                'Total Edificações' => $lote->edificacoes->count(),
                'Foto Frontal' => $lote->foto_frontal ? 'Sim' : 'Não',
                'Foto Lat. Esq.' => $lote->foto_lateral_esq ? 'Sim' : 'Não',
                'Foto Lat. Dir.' => $lote->foto_lateral_dir ? 'Sim' : 'Não',
                'Inconformidade' => $lote->status_cadastro === 'inconformidade'
                    ? ($coleta?->inconformidade_descricao ?? '')
                    : '',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    /**
     * Acima disto o PDF vai para a fila (R84-5): medido na VPS (Cajazeiras), cada lote
     * soma ~0,16 MB ao PDF e ~2 MB de memória ao montar — 250 lotes ≈ 40 MB / ~1 min.
     */
    public const LOTES_POR_PDF = 250;

    public function exportToPdf(Collection $lotes)
    {
        $fileName = 'coletas-produtividade-'.now()->format('YmdHis').'.pdf';
        $conteudo = $this->pdfBinario($lotes, Filament::getTenant());

        return response()->streamDownload(fn () => print ($conteudo), $fileName, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Bytes do PDF detalhado (tela e fila). As fotos são baixadas em paralelo e
     * embutidas em base64 (o DomPDF não lê URL do bucket em produção).
     */
    public function pdfBinario(Collection $lotes, $tenant, ?string $subtitulo = null): string
    {
        $caminhos = $lotes->flatMap(fn ($l) => [$l->foto_frontal, $l->foto_lateral_esq, $l->foto_lateral_dir])->all();
        $fotos = \App\Support\FotosLote::dataUrisEmLote($caminhos);

        $lotes->each(function ($lote) use ($fotos) {
            $lote->_fotos_b64 = [
                'frontal' => $fotos[$lote->foto_frontal] ?? null,
                'lateral_esq' => $fotos[$lote->foto_lateral_esq] ?? null,
                'lateral_dir' => $fotos[$lote->foto_lateral_dir] ?? null,
            ];
        });

        $dataHora = now()->format('d/m/Y H:i:s');

        return Pdf::loadView('pdf.produtividade-detalhado', compact('lotes', 'tenant', 'dataHora', 'subtitulo'))
            ->setPaper('a4', 'portrait')
            ->output();
    }
}
