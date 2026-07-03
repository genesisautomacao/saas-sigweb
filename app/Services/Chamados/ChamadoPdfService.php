<?php

namespace App\Services\Chamados;

use App\Models\Chamado;
use App\Services\Gis\StaticMapService;
use Barryvdh\DomPDF\Facade\Pdf;

class ChamadoPdfService
{
    /**
     * Impressão da solicitação (item 174): dados + mini-mapa de localização +
     * mensagens públicas + respostas do boletim + histórico de alteração de fases.
     */
    public function generate(Chamado $chamado)
    {
        $chamado->loadMissing([
            'categoria', 'fluxo', 'faseAtual',
            'mensagens.remetente', 'historicoFases.fase', 'historicoFases.usuario',
        ]);

        // Mini-mapa de localização (reusa o StaticMapService da impressão da OS)
        $mapa = null;
        $geo = $chamado->geo_json;
        if ($geo && isset($geo->coordinates[0], $geo->coordinates[1])) {
            $mapa = StaticMapService::generate((float) $geo->coordinates[1], (float) $geo->coordinates[0], 17);
        }

        $mensagensPublicas = $chamado->mensagens->where('publica', true)->sortBy('created_at');
        $historico = $chamado->historicoFases->sortBy('created_at');

        $pdf = Pdf::loadView('pdf.chamado', [
            'chamado' => $chamado,
            'mapa' => $mapa,
            'mensagensPublicas' => $mensagensPublicas,
            'historico' => $historico,
        ])->setPaper('a4', 'portrait');

        $fileName = 'chamado-'.($chamado->protocolo ?? $chamado->id).'.pdf';

        return response()->streamDownload(fn () => print ($pdf->stream()), $fileName);
    }
}
