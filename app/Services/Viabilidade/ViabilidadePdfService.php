<?php

namespace App\Services\Viabilidade;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Filament\Facades\Filament;

class ViabilidadePdfService
{
    /**
     * Gera o PDF de Viabilidade.
     * @param array $dadosAnalise (Retorno do ViabilidadeService)
     * @param string|null $mapImageBase64 (String da imagem do mapa capturada via JS)
     */
    public function generatePdf(array $dadosAnalise, ?string $mapImageBase64 = null)
    {
        // 🛑 MÁGICA: Pegando o Tenant ativo do jeito certo no Filament
        $tenant = Filament::getTenant();

        $dataHora = now()->format('d/m/Y H:i:s');
        $protocolo = 'VIA-' . date('Ymd') . '-' . Str::upper(Str::random(4));

        // Remove as barras do número do lote (Ex: "S/N" vira "S-N") para o Windows não dar erro
        $numeroLoteSeguro = str_replace(['/', '\\'], '-', $dadosAnalise['numero_lote']);
        $fileName = 'viabilidade-' . $numeroLoteSeguro . '.pdf';

        // Prepara a imagem do mapa (se vier do JS)
        $mapImage = null;
        if ($mapImageBase64) {
            $mapImage = $mapImageBase64;
        }

        // 🛑 MÁGICA: Caminho da view atualizado para resources/views/pdf/
        $pdf = Pdf::loadView(
            'pdf.viabilidade-template',
            compact('dadosAnalise', 'tenant', 'dataHora', 'protocolo', 'mapImage')
        );

        // Configuração A4 Retrato
        $pdf->setPaper('a4', 'portrait');

        // Retorna o objeto stream para o Livewire fazer o download
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }

    /**
     * Gera o PDF exclusivo para Parcelamento do Solo.
     */
    public function generateParcelamentoPdf(array $dadosAnalise, ?string $mapImageBase64 = null)
    {
        $tenant = Filament::getTenant();
        $dataHora = now()->format('d/m/Y H:i:s');
        $protocolo = 'PARC-' . date('Ymd') . '-' . Str::upper(Str::random(4));

        $numeroLoteSeguro = str_replace(['/', '\\'], '-', $dadosAnalise['numero_lote']);
        $fileName = 'parcelamento-' . $numeroLoteSeguro . '.pdf';

        $mapImage = $mapImageBase64;

        // Aponta para o novo template Blade do Passo 3
        $pdf = Pdf::loadView(
            'pdf.viabilidade-parcelamento',
            compact('dadosAnalise', 'tenant', 'dataHora', 'protocolo', 'mapImage')
        );

        $pdf->setPaper('A4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }

   public function generateUnificacaoPdf(array $dadosAnalise, ?string $mapImageBase64 = null)
    {
        $tenant = Filament::getTenant();
        $dataHora = now()->format('d/m/Y H:i:s');
        $protocolo = 'UNIF-' . date('Ymd') . '-' . Str::upper(Str::random(4));

        $numeroLoteSeguro = str_replace(['/', '\\'], '-', $dadosAnalise['numero_lote'] ?? 'S-N');
        $fileName = 'unificacao-' . $numeroLoteSeguro . '.pdf';

        // 🛑 AQUI ESTÁ O PULO DO GATO: Direcionando para a nova View
        $pdf = Pdf::loadView(
            'pdf.viabilidade-unificacao', // Nome exato do arquivo que criaremos abaixo
            compact('dadosAnalise', 'tenant', 'dataHora', 'protocolo', 'mapImageBase64')
        );

        $pdf->setPaper('A4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }
}