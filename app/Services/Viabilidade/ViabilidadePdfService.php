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
     * Gera o PDF de Viabilidade (funcionamento/uso).
     */
    public function generatePdf(array $dadosAnalise, ?string $mapImageBase64 = null)
    {
        $tenant = Filament::getTenant();
        $dataHora = now()->format('d/m/Y H:i:s');

        $emissao = $this->registrarEmissao('VIA', 'viabilidade', $dadosAnalise);
        $protocolo = $emissao['protocolo'];
        $urlValidacao = $emissao['urlValidacao'];

        $numeroLoteSeguro = str_replace(['/', '\\'], '-', $dadosAnalise['numero_lote'] ?? 'S-N');
        $fileName = 'viabilidade-' . $numeroLoteSeguro . '.pdf';

        $mapImage = $mapImageBase64;

        $pdf = Pdf::loadView(
            'pdf.viabilidade-template',
            compact('dadosAnalise', 'tenant', 'dataHora', 'protocolo', 'mapImage', 'urlValidacao')
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

        $emissao = $this->registrarEmissao('PARC', 'parcelamento', $dadosAnalise);
        $protocolo = $emissao['protocolo'];
        $urlValidacao = $emissao['urlValidacao'];

        $numeroLoteSeguro = str_replace(['/', '\\'], '-', $dadosAnalise['numero_lote'] ?? 'S-N');
        $fileName = 'parcelamento-' . $numeroLoteSeguro . '.pdf';

        $mapImage = $mapImageBase64;

        $pdf = Pdf::loadView(
            'pdf.viabilidade-parcelamento',
            compact('dadosAnalise', 'tenant', 'dataHora', 'protocolo', 'mapImage', 'urlValidacao')
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
        $dadosAnalise = $emissao->dados_snapshot ?? [];
        $protocolo   = $emissao->protocolo;
        $urlValidacao = url("/v/{$protocolo}");
        $mapImage    = null; // sem contexto de mapa no servidor

        $view = match ($emissao->tipo) {
            'parcelamento' => 'pdf.viabilidade-parcelamento',
            'unificacao'   => 'pdf.viabilidade-unificacao',
            default        => 'pdf.viabilidade-template',
        };

        $numeroLoteSeguro = str_replace(['/', '\\'], '-', $dadosAnalise['numero_lote'] ?? 'S-N');
        $fileName = 'reimpr-' . $protocolo . '-' . $numeroLoteSeguro . '.pdf';

        $pdf = Pdf::loadView($view, compact('dadosAnalise', 'tenant', 'dataHora', 'protocolo', 'mapImage', 'urlValidacao'));
        $pdf->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }

    public function generateUnificacaoPdf(array $dadosAnalise, ?string $mapImageBase64 = null)
    {
        $tenant = Filament::getTenant();
        $dataHora = now()->format('d/m/Y H:i:s');

        $emissao = $this->registrarEmissao('UNIF', 'unificacao', $dadosAnalise);
        $protocolo = $emissao['protocolo'];
        $urlValidacao = $emissao['urlValidacao'];

        $numeroLoteSeguro = str_replace(['/', '\\'], '-', $dadosAnalise['numero_lote'] ?? 'S-N');
        $fileName = 'unificacao-' . $numeroLoteSeguro . '.pdf';

        $pdf = Pdf::loadView(
            'pdf.viabilidade-unificacao',
            compact('dadosAnalise', 'tenant', 'dataHora', 'protocolo', 'mapImageBase64', 'urlValidacao')
        );
        $pdf->setPaper('A4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }
}
