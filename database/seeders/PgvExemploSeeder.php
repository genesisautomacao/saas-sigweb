<?php

namespace Database\Seeders;

use App\Services\Pgv\PgvFaceCalculoService;
use App\Services\Pgv\PgvRegressaoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Popula dados de demonstração do motor de Avaliação em Massa (PGV) para a PoC.
 *
 * Ancorado em geometria real do tenant: pólo no centro da malha de lotes,
 * amostras em lotes reais com valor/m² decaindo pela distância ao pólo (1 espúria
 * de outlier para demonstrar "remover espúria"), faces nas bordas de quadras reais,
 * tabela CUB e depreciação. Ao final roda a regressão + cálculo de faces para as
 * faces já aparecerem coloridas no mapa.
 *
 * Idempotente: pula um tenant que já tenha amostras PGV. Alvo:
 *   - env PGV_SEED_TENANT (slug), ou
 *   - todos os tenants com módulo 'pgv' ativo e lotes com geometria.
 *
 * Uso: php artisan db:seed --class=PgvExemploSeeder
 */
class PgvExemploSeeder extends Seeder
{
    public function run(): void
    {
        $slug = env('PGV_SEED_TENANT');

        $tenants = DB::table('tenants')
            ->when($slug, fn ($q) => $q->where('slug', $slug))
            ->get();

        $algum = false;

        foreach ($tenants as $tenant) {
            $modules = json_decode($tenant->modules ?? '[]', true) ?: [];
            if (! $slug && ! in_array('pgv', $modules, true)) {
                continue;
            }

            $bbox = DB::table('lotes')
                ->selectRaw('ST_XMin(ST_Extent(geo)) x0, ST_YMin(ST_Extent(geo)) y0, ST_XMax(ST_Extent(geo)) x1, ST_YMax(ST_Extent(geo)) y1')
                ->where('tenant_id', $tenant->id)
                ->whereNull('deleted_at')
                ->whereNotNull('geo')
                ->first();

            if (! $bbox || $bbox->x0 === null) {
                $this->command?->warn("[PGV] Tenant {$tenant->slug}: sem lotes com geometria — pulado.");
                continue;
            }

            $jaTem = DB::table('pgv_amostras')->where('tenant_id', $tenant->id)->whereNull('deleted_at')->exists();
            if ($jaTem) {
                $this->command?->info("[PGV] Tenant {$tenant->slug}: já possui amostras — pulado (idempotente).");
                continue;
            }

            $this->semear($tenant, $bbox);
            $algum = true;
        }

        if (! $algum) {
            $this->command?->warn('[PGV] Nenhum tenant elegível (módulo pgv + lotes com geo, sem amostras existentes).');
        }
    }

    private function semear(object $tenant, object $bbox): void
    {
        $now = now();
        $poloLon = ($bbox->x0 + $bbox->x1) / 2;
        $poloLat = ($bbox->y0 + $bbox->y1) / 2;
        $pole = "ST_SetSRID(ST_MakePoint($poloLon, $poloLat), 4326)";

        // ---- 1) Pólo valorizante (centro da malha) ----
        DB::table('pgv_polos')->insert([
            'tenant_id'     => $tenant->id,
            'sequential_id' => $this->nextSeq('pgv_polos', $tenant->id),
            'name'          => 'Centro / Praça Central',
            'geo'           => DB::raw($pole),
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // ---- 2) Amostras de mercado em lotes reais, espalhadas por distância ----
        // Todos os lotes ordenados por distância ao pólo — para amostrar em TODA a
        // faixa (não só perto), garantindo que a regressão cubra as faces distantes.
        $lotes = DB::table('lotes')
            ->selectRaw("id, ST_AsGeoJSON(ST_Centroid(geo)) AS c, ST_Distance(ST_Centroid(geo)::geography, $pole::geography) AS d")
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->whereNotNull('geo')
            ->orderBy('d')
            ->get();

        if ($lotes->count() < 6) {
            $this->command?->warn("[PGV] Tenant {$tenant->slug}: poucos lotes — amostras não geradas.");
        } else {
            // 10 lotes uniformemente distribuídos ao longo do ranking de distância
            $qtd = 10;
            $escolhidos = [];
            $passo = ($lotes->count() - 1) / ($qtd - 1);
            for ($i = 0; $i < $qtd; $i++) {
                $escolhidos[] = $lotes[(int) round($i * $passo)];
            }

            $maxD = max(1.0, (float) end($escolhidos)->d);
            $base = 1600.0;                 // R$/m² junto ao pólo
            $slope = (600.0 - $base) / $maxD; // decai até ~R$600/m² na borda
            $conserv = ['Ótimo', 'Bom', 'Regular'];
            $tipos = ['Residencial', 'Comercial'];

            $seq = $this->nextSeq('pgv_amostras', $tenant->id);
            foreach ($escolhidos as $i => $lote) {
                $d = (float) $lote->d;
                $ruido = ((($i * 37) % 11) - 5) * 6; // ±30 determinístico
                $valor = round($base + $slope * $d + $ruido, 2);

                // 1 outlier no meio da faixa (espuria=false p/ ser "descoberto" na demo:
                // ao marcá-lo espúrio e recalcular, o R² sobe visivelmente)
                $espuria = false;
                if ($i === 5) {
                    $valor = round($valor + 550, 2); // fora da tendência
                }

                $geoJson = json_encode(json_decode($lote->c));
                DB::table('pgv_amostras')->insert([
                    'tenant_id'          => $tenant->id,
                    'sequential_id'      => $seq++,
                    'lote_id'            => $lote->id,
                    'valor_m2'           => $valor,
                    'idade_aparente'     => 5 + ($i * 3) % 40,
                    'estado_conservacao' => $conserv[$i % 3],
                    'tipologia'          => $tipos[$i % 2],
                    'padrao_cub'         => 'R1-N',
                    'area_terreno'       => 300 + ($i * 45) % 400,
                    'area_edificacao'    => 90 + ($i * 30) % 220,
                    'espuria'            => $espuria,
                    'observacao'         => 'Amostra de demonstração (PoC)',
                    'geo'                => DB::raw("ST_GeomFromGeoJSON('$geoJson')"),
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]);
            }
        }

        // ---- 3) Tabela CUB ----
        $seqCub = $this->nextSeq('pgv_cubs', $tenant->id);
        $cubs = [
            ['R1-B', 'Alvenaria', 'Baixo', 0.9000, 1650.00],
            ['R1-N', 'Alvenaria', 'Normal', 1.0000, 2100.00],
            ['R1-A', 'Alvenaria', 'Alto', 1.2500, 2850.00],
            ['CAL-8-N', 'Concreto', 'Normal', 1.1000, 2450.00],
            ['CSL-8-A', 'Concreto', 'Alto', 1.3500, 3200.00],
        ];
        foreach ($cubs as $c) {
            DB::table('pgv_cubs')->insert([
                'tenant_id'      => $tenant->id,
                'sequential_id'  => $seqCub++,
                'tipologia'      => $c[0],
                'tipo_estrutura' => $c[1],
                'padrao'         => $c[2],
                'coeficiente'    => $c[3],
                'valor_m2'       => $c[4],
                'mes_referencia' => $now->format('Y-m'),
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        // ---- 4) Depreciação (Ross-Heidecke simplificada por faixa) ----
        $seqDep = $this->nextSeq('pgv_depreciacoes', $tenant->id);
        $deps = [
            ['Ótimo', 0, 10, 1.0000],
            ['Ótimo', 11, 30, 0.9200],
            ['Bom', 0, 10, 0.9500],
            ['Bom', 11, 30, 0.8500],
            ['Regular', 0, 20, 0.8000],
            ['Regular', 21, 50, 0.6500],
            ['Ruim', 0, 100, 0.4500],
        ];
        foreach ($deps as $d) {
            DB::table('pgv_depreciacoes')->insert([
                'tenant_id'          => $tenant->id,
                'sequential_id'      => $seqDep++,
                'estado_conservacao' => $d[0],
                'idade_de'           => $d[1],
                'idade_ate'          => $d[2],
                'coeficiente'        => $d[3],
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        }

        // ---- 5) Faces de quadra (4 segmentos na borda de até 3 quadras reais) ----
        $quadras = DB::table('quadras')
            ->select('id', 'code', 'name')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->whereNotNull('geo')
            ->limit(3)
            ->get();

        $seqFace = $this->nextSeq('face_quadras', $tenant->id);
        foreach ($quadras as $qi => $quadra) {
            $rotulo = $quadra->code ?: ($quadra->name ?: ('Q' . ($qi + 1)));
            $fracoes = [[0, 0.25], [0.25, 0.5], [0.5, 0.75], [0.75, 1.0]];
            foreach ($fracoes as $fi => [$f0, $f1]) {
                // anel externo do 1º polígono → substring da face → MultiLineString
                $faceSql = "ST_Multi(ST_LineSubstring(ST_ExteriorRing(ST_GeometryN(ST_Multi(geo), 1)), $f0, $f1))";
                $row = DB::table('quadras')
                    ->selectRaw("ST_AsGeoJSON($faceSql) AS g, ST_Length($faceSql::geography) AS ext")
                    ->where('id', $quadra->id)
                    ->first();

                if (! $row || ! $row->g) {
                    continue;
                }
                $g = $row->g;

                DB::table('face_quadras')->insert([
                    'tenant_id'     => $tenant->id,
                    'sequential_id' => $seqFace++,
                    'code'          => sprintf('%s-F%d', $rotulo, $fi + 1),
                    'name'          => sprintf('Face %d — Quadra %s', $fi + 1, $rotulo),
                    'quadra_id'     => $quadra->id,
                    'extensao_geo'  => round((float) $row->ext, 2),
                    'geo'           => DB::raw("ST_Multi(ST_GeomFromGeoJSON('$g'))"),
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }

        // ---- 6) Configuração do motor (1 registro/tenant) ----
        if (! DB::table('pgv_configuracoes')->where('tenant_id', $tenant->id)->exists()) {
            DB::table('pgv_configuracoes')->insert([
                'tenant_id'             => $tenant->id,
                'fatores'               => json_encode([
                    'esquina'    => 1.10,
                    'meio_quadra' => 1.00,
                    'encravado'  => 0.85,
                    'global'     => 1.00,
                ]),
                'percentual_valor_venal' => 100,
                'limite_aumento_iptu'    => 30,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);
        }

        // ---- 7) Rodar motores para as faces já aparecerem coloridas ----
        try {
            app(PgvRegressaoService::class)->calcular($tenant->id);
            app(PgvFaceCalculoService::class)->recalcularTodas($tenant->id);
        } catch (\Throwable $e) {
            $this->command?->warn("[PGV] Cálculo automático falhou ({$e->getMessage()}) — rode pelo mapa.");
        }

        $this->command?->info("[PGV] Tenant {$tenant->slug}: dados de demonstração criados (pólo, amostras, CUB, depreciação, faces, config).");
    }

    private function nextSeq(string $table, int $tenantId): int
    {
        return ((int) DB::table($table)->where('tenant_id', $tenantId)->max('sequential_id')) + 1;
    }
}
