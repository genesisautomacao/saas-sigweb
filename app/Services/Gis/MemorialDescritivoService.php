<?php

namespace App\Services\Gis;

use App\Models\Lote;
use Illuminate\Support\Facades\DB;

class MemorialDescritivoService
{
    /**
     * 1. A QUERY MONSTRO (Criada no Passo 1)
     */
    public function gerarDadosPerimetro(int $loteId): array
    {
        $sql = "
            WITH lote_geom AS (
                SELECT geo, tenant_id FROM lotes WHERE id = :lote_id AND deleted_at IS NULL
            ),
            points AS (
                SELECT (ST_DumpPoints(geo)).geom AS pt,
                       (ST_DumpPoints(geo)).path[2] AS pt_idx 
                FROM lote_geom
            ),
            segments AS (
                SELECT
                    p1.pt_idx AS seq,
                    p1.pt AS start_pt,
                    p2.pt AS end_pt,
                    ST_MakeLine(p1.pt, p2.pt) AS geom,
                    ST_Length(ST_MakeLine(p1.pt, p2.pt)::geography) AS distancia,
                    DEGREES(ST_Azimuth(p1.pt, p2.pt)) AS azimute
                FROM points p1
                JOIN points p2 ON p1.pt_idx + 1 = p2.pt_idx
            )
            SELECT
                s.seq,
                ROUND(s.distancia::numeric, 2) AS distancia,
                ROUND(s.azimute::numeric, 4) AS azimute,
                ST_X(s.start_pt) AS start_lon,
                ST_Y(s.start_pt) AS start_lat,
                (
                    SELECT l.numero_lote
                    FROM lotes l
                    WHERE l.tenant_id = (SELECT tenant_id FROM lote_geom)
                      AND l.id != :lote_id_exclude
                      AND l.deleted_at IS NULL
                      AND ST_DWithin(s.geom::geography, l.geo::geography, 0.15)
                    LIMIT 1
                ) AS confrontante_lote,
                (
                    SELECT log.name
                    FROM logradouros log
                    WHERE log.tenant_id = (SELECT tenant_id FROM lote_geom)
                      AND log.deleted_at IS NULL
                      AND ST_DWithin(s.geom::geography, log.geo::geography, 1.0)
                    LIMIT 1
                ) AS confrontante_logradouro
            FROM segments s
            ORDER BY s.seq;
        ";

        return DB::select($sql, [
            'lote_id' => $loteId,
            'lote_id_exclude' => $loteId
        ]);
    }

    /**
     * 2. NOVO: GERA O TEXTO CORRIDO DO MEMORIAL
     */
    public function gerarTextoMemorial(int $loteId): string
    {
        $segmentos = $this->gerarDadosPerimetro($loteId);
        
        if (empty($segmentos)) {
            return "Não foi possível gerar a descrição do perímetro. Geometria inválida ou inexistente.";
        }

        $texto = "Inicia-se a descrição deste perímetro no vértice V1, de coordenadas Longitude " . 
                 number_format($segmentos[0]->start_lon, 6, ',', '.') . " e Latitude " . 
                 number_format($segmentos[0]->start_lat, 6, ',', '.') . "; deste, segue ";

        $totalSegmentos = count($segmentos);

        foreach ($segmentos as $index => $seg) {
            $verticeAtual = $index + 1;
            $proximoVertice = ($index + 1 == $totalSegmentos) ? 1 : $verticeAtual + 1; // Se for o último, volta pro V1
            
            $distancia = number_format($seg->distancia, 2, ',', '.');
            $azimuteDMS = $this->converterGrausMinutosSegundos((float) $seg->azimute);
            $direcao = $this->grausParaDirecao((float) $seg->azimute);

            // Define quem é o vizinho
            if ($seg->confrontante_logradouro) {
                $confrontante = "confrontando com o logradouro " . $seg->confrontante_logradouro;
            } elseif ($seg->confrontante_lote) {
                $confrontante = "confrontando com o Lote " . $seg->confrontante_lote;
            } else {
                $confrontante = "confrontando com área não cadastrada";
            }

            $texto .= "com azimute de $azimuteDMS ($direcao) e distância de {$distancia}m, $confrontante, até o vértice V{$proximoVertice}";
            
            if ($verticeAtual < $totalSegmentos) {
                $texto .= "; deste, deflete e segue ";
            } else {
                $texto .= ", ponto inicial da descrição deste perímetro.";
            }
        }

        return $texto;
    }

    /**
     * 3. NOVO: HELPER TOPOGRÁFICO (Graus para DMS)
     */
    private function converterGrausMinutosSegundos(float $grausDecimais): string
    {
        $graus = floor($grausDecimais);
        $minutosFloat = ($grausDecimais - $graus) * 60;
        $minutos = floor($minutosFloat);
        $segundos = round(($minutosFloat - $minutos) * 60);

        // Ajuste caso o arredondamento jogue o segundo para 60
        if ($segundos == 60) {
            $segundos = 0;
            $minutos += 1;
        }
        if ($minutos == 60) {
            $minutos = 0;
            $graus += 1;
        }

        return sprintf("%02d° %02d' %02d\"", $graus, $minutos, $segundos);
    }

    /**
     * 4. NOVO: HELPER TOPOGRÁFICO (Graus para Pontos Cardeais/Colaterais)
     */
    private function grausParaDirecao(float $graus): string
    {
        if ($graus >= 337.5 || $graus < 22.5) return 'Norte';
        if ($graus >= 22.5 && $graus < 67.5) return 'Nordeste';
        if ($graus >= 67.5 && $graus < 112.5) return 'Leste';
        if ($graus >= 112.5 && $graus < 157.5) return 'Sudeste';
        if ($graus >= 157.5 && $graus < 202.5) return 'Sul';
        if ($graus >= 202.5 && $graus < 247.5) return 'Sudoeste';
        if ($graus >= 247.5 && $graus < 292.5) return 'Oeste';
        if ($graus >= 292.5 && $graus < 337.5) return 'Noroeste';
        
        return '';
    }
}