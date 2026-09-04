<?php

namespace App\Console\Commands;

use App\Models\MobFluxo;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deriva origem_zona/destino_zona dos fluxos O/D pela geometria × zonas O/D
 * (docs/piuma.txt, 2026-09-04). O importador já faz isso ao importar fluxos ou
 * zonas; o comando serve para bases já carregadas (VPS) e para conferência.
 *
 *   php artisan mob:fluxos-origem-destino --tenant=prefeitura-de-piuma
 */
class MobFluxosOrigemDestino extends Command
{
    protected $signature = 'mob:fluxos-origem-destino
                            {--tenant= : Slug do município. Se omitido, roda em todos com o módulo mob_infra.}';

    protected $description = 'Recalcula a zona de origem e de destino de cada fluxo O/D a partir da geometria e das zonas O/D.';

    public function handle(): int
    {
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
            $comGeo = MobFluxo::recalcularOrigensDestinos($tenant->id);

            $semDestino = DB::table('mob_fluxos')->where('tenant_id', $tenant->id)->whereNull('deleted_at')->whereNull('destino_zona')->count();
            $this->line("  linhas com geometria recalculadas: {$comGeo}".($semDestino ? " · ⚠ {$semDestino} sem zona de destino (cadastre as zonas O/D)" : ''));

            $dist = MobFluxo::distribuicao($tenant->id);
            foreach ($dist['destinos'] as $d) {
                $this->line(sprintf('  → %-32s %6.1f%%', $d['label'], $d['percentual']));
            }
            if ($dist['intrazonal'] > 0) {
                $this->line(sprintf('  (intrazonais: %.1f%% do total, sem linha no mapa)', $dist['intrazonal_percentual']));
            }
        }

        $this->info('Concluído.');

        return self::SUCCESS;
    }
}
