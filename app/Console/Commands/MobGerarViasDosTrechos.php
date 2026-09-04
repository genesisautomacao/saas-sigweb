<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gera as Vias Urbanas 1:1 a partir dos Trechos Viários (docs/piuma.txt, Onda 6).
 *
 * Caso Piúma: cada trecho do levantamento vira uma via com a MESMA geometria e o
 * MESMO sequential_id (o JSON do Pedro, por id, casa com as duas tabelas), e o
 * trecho passa a apontar para ela (mob_trechos.via_id). Sentido inicial
 * configurável (padrão mão dupla — a equipe da mobilidade corrige no mapa com
 * a caneta). Idempotente: via já existente com o mesmo número só é vinculada;
 * trecho já vinculado é ignorado.
 *
 *   php artisan mob:gerar-vias-dos-trechos --tenant=prefeitura-de-piuma
 *   php artisan mob:gerar-vias-dos-trechos --tenant=<slug> --sentido=nenhum
 */
class MobGerarViasDosTrechos extends Command
{
    protected $signature = 'mob:gerar-vias-dos-trechos
                            {--tenant= : Slug do município. Se omitido, roda em todos com o módulo mob_infra.}
                            {--sentido=mao_dupla : Sentido inicial das vias criadas: mao_dupla | mao_unica | nenhum (não classificado).}';

    protected $description = 'Cria uma Via Urbana por Trecho Viário (mesma geometria e número) e vincula mob_trechos.via_id.';

    public function handle(): int
    {
        $sentidoOpt = (string) $this->option('sentido');
        if (! in_array($sentidoOpt, ['mao_dupla', 'mao_unica', 'nenhum'], true)) {
            $this->error("--sentido inválido: {$sentidoOpt} (use mao_dupla, mao_unica ou nenhum)");

            return self::FAILURE;
        }
        $sentido = $sentidoOpt === 'nenhum' ? null : $sentidoOpt;

        $tenants = $this->option('tenant')
            ? Tenant::where('slug', $this->option('tenant'))->get()
            : Tenant::all()->filter(fn (Tenant $t) => in_array('mob_infra', $t->modules ?? []));

        if ($tenants->isEmpty()) {
            $this->error($this->option('tenant')
                ? "Tenant '{$this->option('tenant')}' não encontrado."
                : 'Nenhum tenant com o módulo mob_infra.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->line("Tenant: {$tenant->name} (#{$tenant->id})");

            DB::transaction(function () use ($tenant, $sentido) {
                // 1) uma via por trecho VIVO sem via de mesmo número (mesma geometria, mesmo sequential_id)
                $criadas = DB::affectingStatement(
                    'INSERT INTO mob_vias (tenant_id, sequential_id, sentido, geo, created_at, updated_at)
                     SELECT t.tenant_id, t.sequential_id, ?, t.geo, NOW(), NOW()
                       FROM mob_trechos t
                      WHERE t.tenant_id = ? AND t.deleted_at IS NULL AND t.geo IS NOT NULL
                        AND NOT EXISTS (
                            SELECT 1 FROM mob_vias v
                             WHERE v.tenant_id = t.tenant_id AND v.sequential_id = t.sequential_id AND v.deleted_at IS NULL
                        )',
                    [$sentido, $tenant->id]
                );

                // 2) vínculo trecho → via pelo número (só quem ainda não aponta para nenhuma)
                $vinculados = DB::update(
                    'UPDATE mob_trechos t
                        SET via_id = v.id
                       FROM mob_vias v
                      WHERE t.tenant_id = ? AND t.deleted_at IS NULL AND t.via_id IS NULL
                        AND v.tenant_id = t.tenant_id AND v.sequential_id = t.sequential_id AND v.deleted_at IS NULL',
                    [$tenant->id]
                );

                // 3) metadados das vias novas (extensão + azimute = direção da linha)
                $metadata = DB::update(
                    'UPDATE mob_vias SET
                        extensao_geo = ST_Length(geo::geography),
                        azimute = degrees(ST_Azimuth(
                            ST_StartPoint(ST_GeometryN(geo, 1)),
                            ST_EndPoint(ST_GeometryN(geo, ST_NumGeometries(geo)))
                        ))
                      WHERE tenant_id = ? AND geo IS NOT NULL AND (extensao_geo IS NULL OR azimute IS NULL)',
                    [$tenant->id]
                );

                $this->line(sprintf(
                    '  vias criadas: %d (sentido inicial: %s) · trechos vinculados: %d · metadados calculados: %d',
                    $criadas,
                    $sentido ?? 'não classificado',
                    $vinculados,
                    $metadata
                ));
            });

            $semVia = DB::table('mob_trechos')
                ->where('tenant_id', $tenant->id)->whereNull('deleted_at')->whereNull('via_id')->count();
            if ($semVia > 0) {
                $this->warn("  {$semVia} trecho(s) continuam sem via (sem geometria?).");
            }
        }

        $this->info('Concluído.');

        return self::SUCCESS;
    }
}
