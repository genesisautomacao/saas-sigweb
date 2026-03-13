<?php

namespace App\Services\Gis;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;

class CroquiPdfService
{
    public function generatePdf($lote, string $mapImageBase64)
    {
        $tenant = Filament::getTenant(); 
        $dataHora = now()->format('d/m/Y H:i:s');
        $fileName = 'Croqui-Lote-' . ($lote->numero_lote ?? $lote->id) . '.pdf';

        $pdf = Pdf::loadView(
            'pdf.croqui_localizacao', 
            compact('lote', 'mapImageBase64', 'tenant', 'dataHora')
        );

        // O Croqui geralmente fica melhor em formato Retrato (Portrait) com o mapa grande
        $pdf->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }
}