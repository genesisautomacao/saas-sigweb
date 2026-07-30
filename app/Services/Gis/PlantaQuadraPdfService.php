<?php

namespace App\Services\Gis;

use App\Models\Lote;
use App\Models\Quadra;
use App\Services\Coleta\CampoDominioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
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
        $totalLotes = $lotes->count();

        // Distribuição por ocupação seguindo o VOCABULÁRIO DO MUNICÍPIO (R67-2), na ordem
        // configurada. Sem customização resulta exatamente nas células "Construído" e
        // "Baldio" de sempre; com vocabulário próprio, as células acompanham a lista.
        $ocupacaoResumo = $this->distribuicaoOcupacao($lotes);
        $areaTotalLotes = (float) $lotes->sum('area_geo');
        $areaTotalConstr = (float) $lotes->sum(fn ($l) => $l->edificacoes->sum('area_geo'));
        $totalUnidades = (int) $lotes->sum(fn ($l) => $l->unidadesImobiliarias->count());
        $totalEdificacoes = (int) $lotes->sum(fn ($l) => $l->edificacoes->count());

        $dataHora = now()->format('d/m/Y H:i:s');
        $fileName = 'Planta-Quadra-'.preg_replace('/[^\w\-]+/', '_', (string) $quadra->name).'.pdf';

        // Zona UTM SIRGAS 2000 calculada a partir do centróide da quadra
        $sirgasUtmZone = $this->resolveSirgasUtmZone($quadra->id);

        $pdf = Pdf::loadView('pdf.planta-quadra', compact(
            'quadra',
            'lotes',
            'tenant',
            'mapImageBase64',
            'dataHora',
            'totalLotes',
            'ocupacaoResumo',
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
     * Contagem de lotes por valor de ocupação, na ordem do vocabulário do município.
     * Valores gravados que saíram da lista (legado) entram no fim, para não sumirem
     * do somatório da planta.
     *
     * @return array<int, array{label: string, total: int}>
     */
    private function distribuicaoOcupacao(Collection $lotes): array
    {
        $opcoes = CampoDominioService::opcoes('lote', 'ocupacao');

        $contagem = $lotes
            ->filter(fn ($l) => filled($l->ocupacao))
            ->countBy('ocupacao');

        $resumo = [];

        foreach ($opcoes as $valor => $rotulo) {
            $resumo[] = ['label' => $rotulo, 'total' => (int) ($contagem[$valor] ?? 0)];
        }

        foreach ($contagem as $valor => $total) {
            if (! array_key_exists($valor, $opcoes)) {
                $resumo[] = [
                    'label' => CampoDominioService::rotuloValor('lote', 'ocupacao', (string) $valor) ?? (string) $valor,
                    'total' => (int) $total,
                ];
            }
        }

        return $resumo;
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

        if (! $row || $row->lon === null || $row->lat === null) {
            return null;
        }

        $zone = (int) floor(((float) $row->lon + 180) / 6) + 1;
        $hemisferio = ((float) $row->lat) >= 0 ? 'N' : 'S';

        return $zone.$hemisferio;
    }
}
