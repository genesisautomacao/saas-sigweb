<?php

namespace App\Services\Gis;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importação em massa de pontos panorâmicos 360 a partir do GeoJSON de
 * imageamento (padrão Líder: features POINT com image_name, trajectory,
 * azimuth, altitude, start_time).
 *
 * As FOTOS não passam por aqui: sobem direto ao bucket (disk "midia" /
 * Cloudflare R2) via rclone, no caminho DETERMINÍSTICO que este importador
 * grava em cada ponto: {tenant_slug}/panoramicas/{dia}/{image_name}.jpg —
 * onde {dia} é o prefixo do trajectory ("20260727_132711" → "20260727"),
 * espelhando as pastas por dia do fornecedor.
 *
 * IDEMPOTENTE por image_name (= titulo): reimportar o GeoJSON mestre
 * atualizado só cria os pontos novos (os existentes são pulados) — é o fluxo
 * incremental de cada novo lote de captura.
 */
class ImportadorPanoramicasService
{
    private const TAMANHO_LOTE = 500;

    /**
     * @param  array<int, object|array>  $features
     * @return array{total: int, criados: int, pulados: int, invalidos: int}
     */
    public static function importar(Tenant $tenant, array $features): array
    {
        $resumo = ['total' => count($features), 'criados' => 0, 'pulados' => 0, 'invalidos' => 0];

        return DB::transaction(function () use ($tenant, $features, $resumo) {
            // Set de títulos já importados (55k strings cabem tranquilamente em memória)
            $existentes = DB::table('pontos_panoramicos')
                ->where('tenant_id', $tenant->id)
                ->pluck('titulo')
                ->flip()
                ->all();

            $proximoSeq = (int) DB::table('pontos_panoramicos')
                ->where('tenant_id', $tenant->id)
                ->max('sequential_id') + 1;

            $agora = now();
            $lote = [];

            foreach ($features as $feature) {
                $f = is_array($feature) ? json_decode(json_encode($feature)) : $feature;

                $props = $f->properties ?? null;
                $nome = trim((string) ($props->image_name ?? ''));
                $coords = $f->geometry->coordinates ?? null;

                if ($nome === '' || ! is_array($coords) || count($coords) < 2
                    || ! is_numeric($coords[0]) || ! is_numeric($coords[1])) {
                    $resumo['invalidos']++;

                    continue;
                }

                if (isset($existentes[$nome])) {
                    $resumo['pulados']++;

                    continue;
                }
                $existentes[$nome] = true;

                $trajectory = trim((string) ($props->trajectory ?? ''));
                // Pasta do dia: prefixo do trajectory; fallback = 3º bloco do
                // próprio nome (LIDER872_001_20260727_...).
                $pasta = $trajectory !== ''
                    ? explode('_', $trajectory)[0]
                    : (explode('_', $nome)[2] ?? 'sem-data');

                $inicio = (float) ($props->start_time ?? 0);

                $lote[] = [
                    'tenant_id' => $tenant->id,
                    'sequential_id' => $proximoSeq++,
                    'code' => (string) Str::uuid(),
                    'titulo' => $nome,
                    'image_path' => "{$tenant->slug}/panoramicas/{$pasta}/{$nome}.jpg",
                    'data_captura' => $inicio > 0 ? date('Y-m-d H:i:s', (int) $inicio) : null,
                    'azimuth' => is_numeric($props->azimuth ?? null) ? round((float) $props->azimuth, 2) : null,
                    'altitude' => is_numeric($props->altitude ?? null) ? round((float) $props->altitude, 3) : null,
                    'trajectory' => $trajectory !== '' ? $trajectory : null,
                    // Coordenadas numéricas validadas acima — o raw é seguro
                    'geo' => DB::raw("ST_GeomFromGeoJSON('".json_encode([
                        'type' => 'Point',
                        'coordinates' => [(float) $coords[0], (float) $coords[1]],
                    ])."')"),
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
                $resumo['criados']++;

                if (count($lote) >= self::TAMANHO_LOTE) {
                    DB::table('pontos_panoramicos')->insert($lote);
                    $lote = [];
                }
            }

            if ($lote !== []) {
                DB::table('pontos_panoramicos')->insert($lote);
            }

            return $resumo;
        });
    }
}
