<?php

namespace App\Jobs;

use App\Models\ArquivoGerado;
use App\Models\Lote;
use App\Services\Exports\ProdutividadeExportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * R84-5 — PDF detalhado de Coletas Cadastradas em segundo plano: ZIP com PDFs de até
 * ProdutividadeExportService::LOTES_POR_PDF lotes. Parâmetros: lote_ids (o filtro da
 * tabela no momento do pedido) e agrupamento:
 *  - coletor: um PDF por coletor (partes acima do teto), em ordem de data da coleta;
 *  - quadra:  ordem de quadra, PDFs até o teto SEM cortar quadra no meio;
 *  - data:    ordem cronológica, PDFs até o teto.
 */
class GerarPdfColetasJob extends GerarArquivoJob
{
    public const AGRUPAMENTOS = [
        'coletor' => 'Por coletor',
        'quadra' => 'Por quadra',
        'data' => 'Por data (crescente)',
    ];

    protected function gerar(ArquivoGerado $arquivo, string $dirTrabalho): array
    {
        $tenant = $arquivo->tenant;
        $ids = array_map('intval', $arquivo->parametros['lote_ids'] ?? []);
        $agrupamento = $arquivo->parametros['agrupamento'] ?? 'coletor';
        $teto = ProdutividadeExportService::LOTES_POR_PDF;

        // Passo leve: só o necessário para ordenar e agrupar (sem fotos/unidades/edificações)
        $leves = Lote::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->whereNull('deleted_at')->whereIn('id', $ids)
            ->with(['quadra:id,name,sequential_id', 'coletaVigente.coletadoPor:id,name'])
            ->get(['id', 'quadra_id', 'numero_lote', 'sequential_id']);

        if ($leves->isEmpty()) {
            throw new \RuntimeException('Nenhum lote do filtro existe mais.');
        }

        $arquivos = $this->planejar($leves, $agrupamento, $teto);
        $service = app(ProdutividadeExportService::class);
        $pdfs = [];

        foreach ($arquivos as $i => $plano) {
            $lotes = Lote::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)->whereNull('deleted_at')->whereIn('id', $plano['ids'])
                ->with(['quadra', 'zona', 'coletaVigente.coletadoPor', 'unidadesImobiliarias', 'edificacoes'])
                ->get()
                ->sortBy(fn ($l) => array_search($l->id, $plano['ids'], true))
                ->values();

            $caminho = $dirTrabalho.'/'.$plano['nome'];
            file_put_contents($caminho, $service->pdfBinario($lotes, $tenant, $plano['subtitulo']));
            $pdfs[] = $caminho;

            unset($lotes);
            gc_collect_cycles();
            $this->progresso($arquivo, (int) round(95 * ($i + 1) / count($arquivos)));
        }

        $nomeZip = 'coletas-detalhado-'.$tenant->slug.'-'.now()->format('Ymd-His').'.zip';
        $zipPath = $dirTrabalho.'/'.$nomeZip;
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($pdfs as $pdf) {
            // PDF já é comprimido: guardar sem recomprimir (bem mais rápido, mesmo tamanho)
            $zip->addFile($pdf, basename($pdf));
            $zip->setCompressionName(basename($pdf), ZipArchive::CM_STORE);
        }
        $zip->close();

        $resumo = $leves->count().' lote(s) em '.count($pdfs).' PDF(s) — '.mb_strtolower(self::AGRUPAMENTOS[$agrupamento] ?? $agrupamento);

        return [$zipPath, $nomeZip, $resumo];
    }

    /**
     * Divide os lotes em arquivos. @return list<array{nome:string, subtitulo:string, ids:list<int>}>
     */
    public function planejar(Collection $leves, string $agrupamento, int $teto): array
    {
        $dataColeta = fn ($l) => $l->coletaVigente?->coletado_em;
        $chaveQuadra = fn ($l) => [$l->quadra?->name ?? '', (int) ($l->quadra?->sequential_id ?? 0)];

        $arquivos = [];

        if ($agrupamento === 'quadra') {
            $ordenados = $leves->sort(function ($a, $b) use ($chaveQuadra) {
                return strnatcasecmp($chaveQuadra($a)[0], $chaveQuadra($b)[0])
                    ?: $chaveQuadra($a)[1] <=> $chaveQuadra($b)[1]
                    ?: strnatcasecmp((string) $a->numero_lote, (string) $b->numero_lote);
            })->values();

            // Grupos por quadra, empacotados até o teto sem cortar quadra (quadra maior que o teto é dividida)
            $atual = ['ids' => [], 'quadras' => []];
            foreach ($ordenados->groupBy(fn ($l) => $l->quadra_id ?? 0) as $grupo) {
                foreach ($grupo->chunk($teto) as $pedaco) {
                    if (count($atual['ids']) + $pedaco->count() > $teto && $atual['ids']) {
                        $arquivos[] = $atual;
                        $atual = ['ids' => [], 'quadras' => []];
                    }
                    $atual['ids'] = array_merge($atual['ids'], $pedaco->pluck('id')->all());
                    $atual['quadras'][] = $pedaco->first()->quadra?->name ?? 'sem quadra';
                }
            }
            if ($atual['ids']) {
                $arquivos[] = $atual;
            }

            return $this->nomear(array_map(function ($a) {
                $q = array_values(array_unique($a['quadras']));
                $faixa = count($q) > 1 ? reset($q).' a '.end($q) : $q[0];

                return ['base' => 'quadras-'.$faixa, 'subtitulo' => (count($q) > 1 ? 'Quadras ' : 'Quadra ').$faixa, 'ids' => $a['ids']];
            }, $arquivos));
        }

        if ($agrupamento === 'data') {
            $ordenados = $leves->sortBy(fn ($l) => $dataColeta($l)?->timestamp ?? PHP_INT_MAX)->values();
            foreach ($ordenados->chunk($teto) as $pedaco) {
                $ini = $dataColeta($pedaco->first())?->format('d-m-Y') ?? 'sem-data';
                $fim = $dataColeta($pedaco->last())?->format('d-m-Y') ?? 'sem-data';
                $faixa = $ini === $fim ? $ini : $ini.' a '.$fim;
                $arquivos[] = ['base' => 'coletas-'.$faixa, 'subtitulo' => 'Coletas de '.str_replace('-', '/', $faixa), 'ids' => $pedaco->pluck('id')->all()];
            }

            return $this->nomear($arquivos);
        }

        // coletor (padrão)
        $porColetor = $leves->groupBy(fn ($l) => $l->coletaVigente?->coletadoPor?->name ?? 'Sem coletor')->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($porColetor as $nome => $grupo) {
            $pedacos = $grupo->sortBy(fn ($l) => $dataColeta($l)?->timestamp ?? PHP_INT_MAX)->values()->chunk($teto);
            foreach ($pedacos as $n => $pedaco) {
                $parte = $pedacos->count() > 1 ? ' - parte '.($n + 1) : '';
                $arquivos[] = ['base' => 'coletor-'.$nome.$parte, 'subtitulo' => 'Coletor: '.$nome.$parte, 'ids' => $pedaco->pluck('id')->all()];
            }
        }

        return $this->nomear($arquivos);
    }

    /** Nome de arquivo seguro e único dentro do ZIP. */
    private function nomear(array $arquivos): array
    {
        $usados = [];

        return array_map(function ($a) use (&$usados) {
            $base = Str::slug($a['base']) ?: 'coletas';
            $nome = $base;
            for ($i = 2; isset($usados[$nome]); $i++) {
                $nome = $base.'-'.$i;
            }
            $usados[$nome] = true;

            return ['nome' => $nome.'.pdf', 'subtitulo' => $a['subtitulo'], 'ids' => array_values($a['ids'])];
        }, $arquivos);
    }
}
