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

        $fileName = 'viabilidade-' . $dadosAnalise['numero_lote'] . '.pdf';

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
}