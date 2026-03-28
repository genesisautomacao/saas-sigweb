<?php

namespace App\Services\Gis;

use App\Models\Lote;
use Illuminate\Support\Facades\DB;

class MemorialDescritivoService
{
    /**
     * 1. A QUERY MONSTRO: Agora calculando do CENTRO do segmento e exportando em UTM SIRGAS 2000!
     */
    public function gerarDadosPerimetro(int $loteId): array
    {
        $sql = "
            WITH lote_geom AS (
                SELECT geo, tenant_id FROM lotes WHERE id = :lote_id AND deleted_at IS NULL
            ),
            dumped AS (
                SELECT l.tenant_id, dp.path, dp.geom AS pt
                FROM lote_geom l, ST_DumpPoints(l.geo) AS dp
            ),
            points AS (
                SELECT tenant_id, pt, ROW_NUMBER() OVER(ORDER BY path) AS pt_idx 
                FROM dumped
            ),
            segments AS (
                SELECT
                    p1.pt_idx AS seq,
                    p1.pt AS start_pt,
                    p2.pt AS end_pt,
                    ST_MakeLine(p1.pt, p2.pt) AS geom,
                    ST_LineInterpolatePoint(ST_MakeLine(p1.pt, p2.pt), 0.5) AS midpoint, -- Ponto exato no centro do muro!
                    ST_Length(ST_MakeLine(p1.pt, p2.pt)::geography) AS distancia,
                    DEGREES(ST_Azimuth(p1.pt, p2.pt)) AS azimute
                FROM points p1
                JOIN points p2 ON p1.pt_idx + 1 = p2.pt_idx
            )
            SELECT
                ROW_NUMBER() OVER(ORDER BY s.seq) AS seq,
                ROUND(s.distancia::numeric, 2) AS distancia,
                ROUND(s.azimute::numeric, 4) AS azimute,
                
                -- 🛑 A MÁGICA TOPOGRÁFICA AQUI: Conversão para SIRGAS 2000 / UTM 22S (EPSG: 31982)
                ST_X(ST_Transform(s.start_pt, 31982)) AS start_e,
                ST_Y(ST_Transform(s.start_pt, 31982)) AS start_n,
                
                -- Busca o Lote vizinho mais próximo do CENTRO do muro (Tolerância base de 2m)
                (
                    SELECT l.numero_lote FROM lotes l
                    WHERE l.tenant_id = (SELECT tenant_id FROM lote_geom) AND l.id != :lote_id_exclude AND l.deleted_at IS NULL
                      AND ST_DWithin(s.midpoint::geography, l.geo::geography, 2.0)
                    ORDER BY ST_Distance(s.midpoint::geography, l.geo::geography) ASC LIMIT 1
                ) AS confrontante_lote,
                
                -- Retorna a que distância EXATA esse lote está do centro
                (
                    SELECT ST_Distance(s.midpoint::geography, l.geo::geography) FROM lotes l
                    WHERE l.tenant_id = (SELECT tenant_id FROM lote_geom) AND l.id != :lote_id_exclude AND l.deleted_at IS NULL
                      AND ST_DWithin(s.midpoint::geography, l.geo::geography, 2.0)
                    ORDER BY ST_Distance(s.midpoint::geography, l.geo::geography) ASC LIMIT 1
                ) AS dist_lote,
                
                -- Busca a Rua mais próxima (Tolerância aumentada para 15 metros, útil caso a rua seja desenhada apenas no eixo)
                (
                    SELECT log.name FROM logradouros log
                    WHERE log.tenant_id = (SELECT tenant_id FROM lote_geom) AND log.deleted_at IS NULL
                      AND ST_DWithin(s.midpoint::geography, log.geo::geography, 15.0)
                    ORDER BY ST_Distance(s.midpoint::geography, log.geo::geography) ASC LIMIT 1
                ) AS confrontante_logradouro,
                
                -- Retorna a que distância EXATA a rua está do centro
                (
                    SELECT ST_Distance(s.midpoint::geography, log.geo::geography) FROM logradouros log
                    WHERE log.tenant_id = (SELECT tenant_id FROM lote_geom) AND log.deleted_at IS NULL
                      AND ST_DWithin(s.midpoint::geography, log.geo::geography, 15.0)
                    ORDER BY ST_Distance(s.midpoint::geography, log.geo::geography) ASC LIMIT 1
                ) AS dist_logradouro

            FROM segments s
            WHERE s.distancia > 0.01
            ORDER BY seq;
        ";

        return DB::select($sql, [
            'lote_id' => $loteId,
            'lote_id_exclude' => $loteId
        ]);
    }

    /**
     * 2. GERA O TEXTO CORRIDO DO MEMORIAL (Atualizado para Metros/UTM)
     */
    public function gerarTextoMemorial(int $loteId): string
    {
        $segmentos = $this->gerarDadosPerimetro($loteId);
        
        if (empty($segmentos)) {
            return "Não foi possível gerar a descrição do perímetro. Geometria inválida ou inexistente.";
        }

        // 🛑 Atualizado de Lat/Lon para Coordenadas Planas (E, N)
        $texto = "Inicia-se a descrição deste perímetro no vértice V1, definido pelas coordenadas planas UTM E: " . 
                 number_format($segmentos[0]->start_e, 2, ',', '.') . " m e N: " . 
                 number_format($segmentos[0]->start_n, 2, ',', '.') . " m, referenciadas ao Sistema Geodésico SIRGAS 2000, Fuso 22S; deste, segue ";

        $totalSegmentos = count($segmentos);

        foreach ($segmentos as $index => $seg) {
            $verticeAtual = $index + 1;
            $proximoVertice = ($index + 1 == $totalSegmentos) ? 1 : $verticeAtual + 1;
            
            $distancia = number_format($seg->distancia, 2, ',', '.');
            $azimuteDMS = $this->converterGrausMinutosSegundos((float) $seg->azimute);
            $direcao = $this->grausParaDirecao((float) $seg->azimute);

            $temLote = !is_null($seg->confrontante_lote) && $seg->dist_lote <= 1.5; 
            $temLogradouro = !is_null($seg->confrontante_logradouro) && $seg->dist_logradouro <= 15.0;

            if ($temLote) {
                $confrontante = "confrontando com o Lote " . $seg->confrontante_lote;
            } elseif ($temLogradouro) {
                $confrontante = "confrontando com o Logradouro " . $seg->confrontante_logradouro;
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
     * 3. HELPER TOPOGRÁFICO (Graus para DMS)
     */
    private function converterGrausMinutosSegundos(float $grausDecimais): string
    {
        $graus = floor($grausDecimais);
        $minutosFloat = ($grausDecimais - $graus) * 60;
        $minutos = floor($minutosFloat);
        $segundos = round(($minutosFloat - $minutos) * 60);

        if ($segundos == 60) { $segundos = 0; $minutos += 1; }
        if ($minutos == 60) { $minutos = 0; $graus += 1; }

        return sprintf("%02d° %02d' %02d\"", $graus, $minutos, $segundos);
    }

    /**
     * 4. HELPER TOPOGRÁFICO (Graus para Rosa dos Ventos)
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