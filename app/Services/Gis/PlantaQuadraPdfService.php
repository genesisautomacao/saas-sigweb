<?php

namespace App\Services\Gis;

use App\Models\Lote;
use App\Models\Quadra;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

/**
 * Planta de Quadra — TR Tangará item Intranet #16.
 *
 * Gera o relatório-síntese de uma quadra:
 *  - Cabeçalho com identificação (Quadra, Bairro, Loteamento, Distrito, Setor)
 *  - Croqui (opcional — capturado do mapa via html2canvas quando disponível)
 *  - Resumo quantitativo
 *  - Tabela de lotes com áreas/testadas/ocupação
 *  - Por lote: subtabela de edificações
 *  - Unidades imobiliárias (inscrição, logradouro, número)
 */
class PlantaQuadraPdfService
{
    public function generatePdf(int $quadraId, ?string $mapImageBase64 = null)
    {
        $tenant = Filament::getTenant();

        $quadra = Quadra::query()
            ->with(['bairro', 'loteamento', 'perimetro'])
            ->findOrFail($quadraId);

        $lotes = Lote::query()
            ->where('quadra_id', $quadra->id)
            ->with(['unidadesImobiliarias', 'edificacoes', 'zona'])
            ->orderByRaw("NULLIF(regexp_replace(numero_lote, '[^0-9]', '', 'g'), '')::int NULLS LAST")
            ->orderBy('numero_lote')
            ->get();

        // Métricas agregadas
        $totalLotes        = $lotes->count();
        $totalConstruidos  = $lotes->where('ocupacao', 'construido')->count();
        $totalBaldios      = $lotes->where('ocupacao', 'baldio')->count();
        $areaTotalLotes    = (float) $lotes->sum('area_geo');
        $areaTotalConstr   = (float) $lotes->sum(fn ($l) => $l->edificacoes->sum('area_geo'));
        $totalUnidades     = (int)   $lotes->sum(fn ($l) => $l->unidadesImobiliarias->count());
        $totalEdificacoes  = (int)   $lotes->sum(fn ($l) => $l->edificacoes->count());

        $dataHora = now()->format('d/m/Y H:i:s');
        $fileName = 'Planta-Quadra-' . preg_replace('/[^\w\-]+/', '_', (string) $quadra->name) . '.pdf';

        // Zona UTM SIRGAS 2000 calculada a partir do centróide da quadra
        $sirgasUtmZone = $this->resolveSirgasUtmZone($quadra->id);

        $pdf = Pdf::loadView('pdf.planta-quadra', compact(
            'quadra',
            'lotes',
            'tenant',
            'mapImageBase64',
            'dataHora',
            'totalLotes',
            'totalConstruidos',
            'totalBaldios',
            'areaTotalLotes',
            'areaTotalConstr',
            'totalUnidades',
            'totalEdificacoes',
            'sirgasUtmZone',
        ));

        // A3 paisagem dá espaço de sobra para croqui + tabelas no mesmo documento.
        $pdf->setPaper('a3', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }

    /**
     * Calcula a zona UTM SIRGAS 2000 (ex.: "22S") a partir do centróide da quadra.
     * Retorna null se a quadra não tiver geometria.
     */
    private function resolveSirgasUtmZone(int $quadraId): ?string
    {
        $row = DB::selectOne(
            'SELECT ST_X(ST_Centroid(geo)) AS lon, ST_Y(ST_Centroid(geo)) AS lat
             FROM quadras WHERE id = ? AND geo IS NOT NULL LIMIT 1',
            [$quadraId]
        );

        if (!$row || $row->lon === null || $row->lat === null) {
            return null;
        }

        $zone       = (int) floor(((float) $row->lon + 180) / 6) + 1;
        $hemisferio = ((float) $row->lat) >= 0 ? 'N' : 'S';

        return $zone . $hemisferio;
    }
}
