<?php

namespace App\Services\Gis;

use App\Models\UnidadeImobiliaria;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;

class BicPdfService
{
    // Método original (Impressão Única)
    public function generatePdf(int $unidadeId, ?string $mapImageBase64 = null)
    {
        $tenant = Filament::getTenant();
        $imovel = UnidadeImobiliaria::with(['lote.quadra', 'proprietario'])->findOrFail($unidadeId);
        $dadosJson = $imovel->dados_tributarios ?? [];
        $dataHora = now()->format('d/m/Y H:i:s');
        $fileName = 'BCI-'.($imovel->codigo_imovel_tributario ?? $imovel->id).'.pdf';

        // Converte foto frontal do lote para base64 (DomPDF não aceita asset() em produção)
        $fotoFrontalBase64 = null;
        $fotoPath = $imovel->lote?->foto_frontal;
        if ($fotoPath && Storage::disk('public')->exists($fotoPath)) {
            $mime = Storage::disk('public')->mimeType($fotoPath);
            $fotoFrontalBase64 = 'data:'.$mime.';base64,'.
                base64_encode(Storage::disk('public')->get($fotoPath));
        }

        // R67-1/5 — campos criados pelo município + campos extras do sistema tributário local
        $camposMunicipio = array_merge(
            \App\Services\Coleta\CampoCustomizadoService::colunasExport('unidade', $imovel->dados_customizados, $tenant?->id),
            \App\Services\Coleta\CampoCustomizadoService::colunasExport('lote', $imovel->lote?->dados_customizados, $tenant?->id),
        );
        $extrasFiscais = $tenant ? \App\Services\Fiscal\MapaFiscalService::extras($dadosJson, $tenant->id) : [];

        $pdf = Pdf::loadView(
            'pdf.bic-template',
            compact('imovel', 'dadosJson', 'mapImageBase64', 'fotoFrontalBase64', 'tenant', 'dataHora', 'camposMunicipio', 'extrasFiscais')
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
        $fileName = 'BCIs-Em-Lote-'.now()->format('YmdHis').'.pdf';

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
