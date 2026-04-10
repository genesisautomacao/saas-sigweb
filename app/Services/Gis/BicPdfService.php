<?php

namespace App\Services\Gis;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\UnidadeImobiliaria;
use Filament\Facades\Filament;

class BicPdfService
{
    // Método original (Impressão Única)
    public function generatePdf(int $unidadeId, ?string $mapImageBase64 = null)
    {
        $tenant = Filament::getTenant(); 
        $imovel = UnidadeImobiliaria::with(['lote.quadra', 'proprietario'])->findOrFail($unidadeId);
        $dadosJson = $imovel->dados_tributarios ?? [];
        $dataHora = now()->format('d/m/Y H:i:s');
        $fileName = 'BCI-' . ($imovel->codigo_imovel_tributario ?? $imovel->id) . '.pdf';

        $pdf = Pdf::loadView(
            'pdf.bic-template',
            compact('imovel', 'dadosJson', 'mapImageBase64', 'tenant', 'dataHora')
        );

        $pdf->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }

    // 🟢 NOVO MÉTODO: Impressão em Massa (Múltiplas páginas no mesmo PDF)
    public function generatePdfEmMassa(array $unidadesIds, ?string $mapImageBase64 = null)
    {
        $tenant = Filament::getTenant(); 
        
        // Carrega todas as unidades de uma vez só com suas relações
        $imoveis = UnidadeImobiliaria::with(['lote.quadra', 'proprietario'])
            ->whereIn('id', $unidadesIds)
            ->get();

        $dataHora = now()->format('d/m/Y H:i:s');
        $fileName = 'BCIs-Em-Lote-' . now()->format('YmdHis') . '.pdf';

        // Chama uma nova View Blade preparada para fazer o loop de páginas
        $pdf = Pdf::loadView(
            'pdf.bic-massa-template', 
            compact('imoveis', 'mapImageBase64', 'tenant', 'dataHora')
        );

        $pdf->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }
}