<?php

namespace App\Services\Gis;

use App\Models\PontoPanoramico;
use Illuminate\Support\Facades\DB;

/**
 * Navegação estilo STREET VIEW das panorâmicas 360 — FONTE ÚNICA do viewer do painel
 * da prefeitura (HasPontoPanoramicoActions) e do mapa público (MapaPublico).
 * Extraído do trait interno em 2026-09-21, quando o público ganhou as setas.
 *
 * Vizinhos navegáveis: da MESMA trajetória, só o ponto imediatamente anterior/seguinte
 * (andar pela rua); de OUTRAS trajetórias, o mais próximo de cada uma num raio de 15 m
 * (virar no cruzamento). Direção (bearing) e distância calculadas no PostGIS; setas
 * quase na mesma direção (< 20°) são deduplicadas ficando a primeira. Máximo de 4.
 *
 * A URL vem do accessor `imagem_url` (simulação → R2 assinado → storage local) e é
 * assinada SÓ para o ponto atual — nunca para os vizinhos (um passo = uma assinatura).
 * `url` null = foto ainda não enviada ao bucket; quem chama decide o que mostrar.
 * Os vizinhos são sempre do MESMO tenant do ponto de referência.
 */
class PanoramaNavegacaoService
{
    public const RAIO_METROS = 15;

    public const MAX_SETAS = 4;

    /** @return array{id:int, titulo:?string, url:?string, azimuth:float, setas:array<int, array{id:int, bearing:float, dist:float}>} */
    public static function dados(PontoPanoramico $ponto): array
    {
        $candidatos = DB::select('
            SELECT p.id, p.titulo, p.trajectory,
                   ST_Distance(p.geo::geography, ref.geo::geography) AS dist,
                   degrees(ST_Azimuth(ref.geo::geometry, p.geo::geometry)) AS bearing
            FROM pontos_panoramicos p
            CROSS JOIN (SELECT geo FROM pontos_panoramicos WHERE id = ?) ref
            WHERE p.tenant_id = ?
              AND p.id <> ?
              AND p.deleted_at IS NULL
              AND p.geo IS NOT NULL
              AND ST_DWithin(p.geo::geography, ref.geo::geography, ?)
            ORDER BY dist
            LIMIT 12
        ', [$ponto->id, $ponto->tenant_id, $ponto->id, self::RAIO_METROS]);

        // Mesma trajetória: o nome do arquivo é sequencial → o vizinho imediato
        // em cada sentido é o de menor distância com título maior/menor.
        $mesma = collect($candidatos)->filter(fn ($c) => $c->trajectory === $ponto->trajectory);
        $proximo = $mesma->filter(fn ($c) => $c->titulo > $ponto->titulo)->sortBy('dist')->first();
        $anterior = $mesma->filter(fn ($c) => $c->titulo < $ponto->titulo)->sortBy('dist')->first();

        // Cruzamentos: o ponto mais próximo de CADA outra trajetória
        $cruzamentos = collect($candidatos)
            ->filter(fn ($c) => $c->trajectory !== $ponto->trajectory)
            ->groupBy('trajectory')
            ->map(fn ($grupo) => $grupo->sortBy('dist')->first())
            ->values();

        $setas = [];

        foreach (collect([$proximo, $anterior])->merge($cruzamentos)->filter() as $c) {
            $bearing = fmod((float) $c->bearing + 360, 360);

            // Duas setas praticamente na mesma direção = fica só a primeira
            // (anterior/próximo têm prioridade sobre cruzamentos).
            foreach ($setas as $s) {
                $delta = abs($s['bearing'] - $bearing);
                if (min($delta, 360 - $delta) < 20) {
                    continue 2;
                }
            }

            $setas[] = [
                'id' => (int) $c->id,
                'bearing' => round($bearing, 1),
                'dist' => round((float) $c->dist, 1),
            ];

            if (count($setas) >= self::MAX_SETAS) {
                break;
            }
        }

        return [
            'id' => $ponto->id,
            'titulo' => $ponto->titulo,
            'url' => $ponto->imagem_url,
            'azimuth' => (float) ($ponto->azimuth ?? 0),
            'setas' => $setas,
        ];
    }
}
