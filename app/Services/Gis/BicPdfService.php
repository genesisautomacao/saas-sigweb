<?php

namespace App\Services\Gis;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\UnidadeImobiliaria;
use Filament\Facades\Filament;

class BicPdfService
{
    public function generatePdf(int $unidadeId, ?string $mapImageBase64 = null)
    {
        // Pega o Tenant atual de forma segura pelo contexto do Filament
        $tenant = Filament::getTenant(); 
        
        // Carrega a unidade com o Lote (para sabermos a Quadra e o Nº do Lote) e o Proprietário
        $imovel = UnidadeImobiliaria::with(['lote.quadra', 'proprietario'])->findOrFail($unidadeId);

        // Extrai os dados do JSON que salvamos na Sincronização
        $dadosJson = $imovel->dados_tributarios ?? [];

        $dataHora = now()->format('d/m/Y H:i:s');
        $fileName = 'BCI-' . ($imovel->codigo_imovel_tributario ?? $imovel->id) . '.pdf';

        $pdf = Pdf::loadView(
            'pdf.bic-template', // 🛑 ATENÇÃO: Renomeei o caminho para uma pasta mais limpa!
            compact('imovel', 'dadosJson', 'mapImageBase64', 'tenant', 'dataHora')
        );

        $pdf->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }
}