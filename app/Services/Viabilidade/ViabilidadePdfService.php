<?php

namespace App\Services\Viabilidade;

use App\Models\ViabilidadeEmissao;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

class ViabilidadePdfService
{
    /**
     * Registra a emissão no banco e retorna [protocolo, urlValidacao, hash].
     * Snapshot é gravado para permitir validação pública posterior (#21/#14 TR Tangará).
     */
    protected function registrarEmissao(string $tipoPrefixo, string $tipo, array $dadosAnalise): array
    {
        $tenantId = $this->resolverTenantId($dadosAnalise);

        $protocolo = ViabilidadeEmissao::gerarProtocolo($tipoPrefixo);
        $hash = hash('sha256', $protocolo . '|' . ($tenantId ?? 0) . '|' . json_encode($dadosAnalise) . '|' . config('app.key'));

        ViabilidadeEmissao::create([
            'tenant_id'              => $tenantId,
            'protocolo'              => $protocolo,
            'hash_seguranca'         => $hash,
            'tipo'                   => $tipo,
            'status'                 => $dadosAnalise['status'] ?? $dadosAnalise['resultado'] ?? null,
            'numero_lote'            => $dadosAnalise['numero_lote'] ?? null,
            'inscricao_imobiliaria'  => $dadosAnalise['inscricao_imobiliaria'] ?? null,
            'lote_id'                => $dadosAnalise['lote_id'] ?? null,
            'unidade_imobiliaria_id' => $dadosAnalise['unidade_imobiliaria_id'] ?? null,
            'dados_snapshot'         => $dadosAnalise,
            'emitido_por'            => auth()->id(),
        ]);

        return [
            'protocolo'    => $protocolo,
            'urlValidacao' => url("/v/{$protocolo}"),
            'hash'         => $hash,
        ];
    }

    /**
     * Descobre o tenant_id em cascata:
     *  1. Tenant ativo no Filament (modo logado / intranet)
     *  2. tenant_id do Lote referenciado em $dadosAnalise (modo público / cidadão anônimo)
     *  3. tenant_id da Unidade Imobiliária referenciada
     */
    protected function resolverTenantId(array $dadosAnalise): ?int
    {
        $tenant = Filament::getTenant();
        if ($tenant?->id) {
            return $tenant->id;
        }

        if (!empty($dadosAnalise['lote_id'])) {
            $tenantId = \Illuminate\Support\Facades\DB::table('lotes')
                ->where('id', $dadosAnalise['lote_id'])
                ->value('tenant_id');
            if ($tenantId) {
                return (int) $tenantId;
            }
        }

        if (!empty($dadosAnalise['unidade_imobiliaria_id'])) {
            $tenantId = \Illuminate\Support\Facades\DB::table('unidade_imobiliarias')
                ->where('id', $dadosAnalise['unidade_imobiliaria_id'])
                ->value('tenant_id');
            if ($tenantId) {
                return (int) $tenantId;
            }
        }

        return null;
    }

    /**
     * T1.5 (item 21) — metragens do imóvel e parâmetros urbanísticos da zona no PDF.
     * Enriquece ANTES do registrarEmissao: entra no snapshot → a reimpressão herda.
     * Idempotente: se as chaves já existem (reimpressão de snapshot novo), não recalcula.
     */
    protected function enriquecerComMetragensEParametros(array $dadosAnalise): array
    {
        $loteId = $dadosAnalise['lote_id'] ?? null;

        if ($loteId && ! isset($dadosAnalise['metragens'])) {
            $lote = \Illuminate\Support\Facades\DB::table('lotes')->where('id', $loteId)->first();

            if ($lote) {
                $dadosAnalise['metragens'] = [
                    'area_lote' => $lote->area_geo !== null ? (float) $lote->area_geo : null,
                    'testada' => $lote->main_facade_length !== null ? (float) $lote->main_facade_length : null,
                    'area_construida' => (float) \Illuminate\Support\Facades\DB::table('edificacoes')
                        ->where('lote_id', $loteId)->whereNull('deleted_at')->sum('area_geo'),
                ];

                if ($lote->zona_id && ! isset($dadosAnalise['parametros_zona'])) {
                    $parametro = \App\Models\ParametroUrbano::query()
                        ->withoutGlobalScopes()
                        ->where('zona_id', $lote->zona_id)
                        ->first();

                    if ($parametro) {
                        $dadosAnalise['parametros_zona'] = [
                            'area_minima' => $parametro->area_minima,
                            'area_maxima' => $parametro->area_maxima,
                            'testada_minima' => $parametro->testada_minima,
                            'testada_maxima' => $parametro->testada_maxima,
                        ];
                    }
                }
            }
        }

        return $dadosAnalise;
    }

    /**
     * T1.5 (item 3-14) — persiste o print do mapa para a REIMPRESSÃO ter o croqui.
     * Antes a reimpressão saía com $mapImage = null ("sem contexto de mapa no servidor").
     */
    protected function persistirMapa(string $protocolo, ?string $mapImageDataUri): void
    {
        if (blank($mapImageDataUri)) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Storage::disk('public')
                ->put("viabilidades/{$protocolo}.b64", $mapImageDataUri);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Viabilidade {$protocolo}: falha ao persistir o mapa — ".$e->getMessage());
        }
    }

    /**
     * Item Internet 14 ("Viabilidade com IMAGEM, croqui, ...") — foto frontal
     * do imóvel em base64 p/ o DomPDF (mesmo padrão do BicPdfService).
     * Resolvida na hora do render (não vai ao snapshot): a reimpressão sai
     * com a foto vigente do lote; sem foto = seção não aparece (degradação limpa).
     */
    protected function fotoFrontalBase64(array $dadosAnalise): ?string
    {
        $loteId = $dadosAnalise['lote_id'] ?? null;
        if (! $loteId) {
            return null;
        }

        try {
            $fotoPath = \Illuminate\Support\Facades\DB::table('lotes')
                ->where('id', $loteId)->value('foto_frontal');

            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if ($fotoPath && $disk->exists($fotoPath)) {
                return 'data:'.$disk->mimeType($fotoPath).';base64,'
                    .base64_encode($disk->get($fotoPath));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Viabilidade: falha ao carregar foto frontal — '.$e->getMessage());
        }

        return null;
    }

    protected function recuperarMapa(string $protocolo): ?string
    {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');

            return $disk->exists("viabilidades/{$protocolo}.b64")
                ? $disk->get("viabilidades/{$protocolo}.b64")
                : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Gera o PDF de Viabilidade (funcionamento/uso).
     */
    public function generatePdf(array $dadosAnalise, ?string $mapImageBase64 = null)
    {
        $tenant = Filament::getTenant();
        $dataHora = now()->format('d/m/Y H:i:s');

        $dadosAnalise = $this->enriquecerComMetragensEParametros($dadosAnalise);

        $emissao = $this->registrarEmissao('VIA', 'viabilidade', $dadosAnalise);
        $protocolo = $emissao['protocolo'];
        $urlValidacao = $emissao['urlValidacao'];

        $this->persistirMapa($protocolo, $mapImageBase64);

        $numeroLoteSeguro = str_replace(['/', '\\'], '-', $dadosAnalise['numero_lote'] ?? 'S-N');
        $fileName = 'viabilidade-' . $numeroLoteSeguro . '.pdf';

        $mapImage = $mapImageBase64;
        $fotoFrontal = $this->fotoFrontalBase64($dadosAnalise);

        $pdf = Pdf::loadView(
            'pdf.viabilidade-template',
            compact('dadosAnalise', 'tenant', 'dataHora', 'protocolo', 'mapImage', 'urlValidacao', 'fotoFrontal')
        );
        $pdf->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }

    public function generateParcelamentoPdf(array $dadosAnalise, ?string $mapImageBase64 = null)
    {
        $tenant = Filament::getTenant();
        $dataHora = now()->format('d/m/Y H:i:s');

        $dadosAnalise = $this->enriquecerComMetragensEParametros($dadosAnalise);

        $emissao = $this->registrarEmissao('PARC', 'parcelamento', $dadosAnalise);
        $protocolo = $emissao['protocolo'];
        $urlValidacao = $emissao['urlValidacao'];

        $this->persistirMapa($protocolo, $mapImageBase64);

        $numeroLoteSeguro = str_replace(['/', '\\'], '-', $dadosAnalise['numero_lote'] ?? 'S-N');
        $fileName = 'parcelamento-' . $numeroLoteSeguro . '.pdf';

        $mapImage = $mapImageBase64;
        $fotoFrontal = $this->fotoFrontalBase64($dadosAnalise);

        $pdf = Pdf::loadView(
            'pdf.viabilidade-parcelamento',
            compact('dadosAnalise', 'tenant', 'dataHora', 'protocolo', 'mapImage', 'urlValidacao', 'fotoFrontal')
        );
        $pdf->setPaper('A4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }

    /**
     * Reimprime PDF de uma emissão existente usando o protocolo/hash originais.
     * Não cria nova emissão no banco.
     */
    public function reimprimirPdf(ViabilidadeEmissao $emissao)
    {
        $tenant      = Filament::getTenant();
        $dataHora    = now()->format('d/m/Y H:i:s');
        // Emissões antigas não têm metragens no snapshot — enriquece na hora (idempotente).
        $dadosAnalise = $this->enriquecerComMetragensEParametros($emissao->dados_snapshot ?? []);
        $protocolo   = $emissao->protocolo;
        $urlValidacao = url("/v/{$protocolo}");
        // T1.5 (item 3-14): recupera o print do mapa persistido na emissão original.
        $mapImage    = $this->recuperarMapa($protocolo);
        $fotoFrontal = $this->fotoFrontalBase64($dadosAnalise);

        $view = match ($emissao->tipo) {
            'parcelamento' => 'pdf.viabilidade-parcelamento',
            'unificacao'   => 'pdf.viabilidade-unificacao',
            default        => 'pdf.viabilidade-template',
        };

        $numeroLoteSeguro = str_replace(['/', '\\'], '-', $dadosAnalise['numero_lote'] ?? 'S-N');
        $fileName = 'reimpr-' . $protocolo . '-' . $numeroLoteSeguro . '.pdf';

        $pdf = Pdf::loadView($view, compact('dadosAnalise', 'tenant', 'dataHora', 'protocolo', 'mapImage', 'urlValidacao', 'fotoFrontal'));
        $pdf->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }

    public function generateUnificacaoPdf(array $dadosAnalise, ?string $mapImageBase64 = null)
    {
        $tenant = Filament::getTenant();
        $dataHora = now()->format('d/m/Y H:i:s');

        $dadosAnalise = $this->enriquecerComMetragensEParametros($dadosAnalise);

        $emissao = $this->registrarEmissao('UNIF', 'unificacao', $dadosAnalise);
        $protocolo = $emissao['protocolo'];
        $urlValidacao = $emissao['urlValidacao'];

        $this->persistirMapa($protocolo, $mapImageBase64);

        $numeroLoteSeguro = str_replace(['/', '\\'], '-', $dadosAnalise['numero_lote'] ?? 'S-N');
        $fileName = 'unificacao-' . $numeroLoteSeguro . '.pdf';

        $fotoFrontal = $this->fotoFrontalBase64($dadosAnalise);

        $pdf = Pdf::loadView(
            'pdf.viabilidade-unificacao',
            compact('dadosAnalise', 'tenant', 'dataHora', 'protocolo', 'mapImageBase64', 'urlValidacao', 'fotoFrontal')
        );
        $pdf->setPaper('A4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }
}
