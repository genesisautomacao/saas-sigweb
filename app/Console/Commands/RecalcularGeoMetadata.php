<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalcularGeoMetadata extends Command
{
    protected $signature = 'gis:recalcular-metadata
                            {--tenant= : Slug do município. Se omitido, roda em todos.}
                            {--entidade= : Nome da entidade (perimetros_urbanos, zonas, bairros, loteamentos, quadras, logradouros). Se omitido, roda nas seis.}
                            {--force : Recalcula mesmo registros que já têm valor (sobrescreve).}';

    protected $description = 'Calcula area_geo (polígonos) e extensao_geo (linhas) via PostGIS para registros sem valor.';

    /**
     * Mapa de entidades suportadas: tabela → [coluna, função PostGIS].
     */
    private const ENTIDADES = [
        'perimetros_urbanos' => ['area_geo',     'ST_Area'],
        'zonas'              => ['area_geo',     'ST_Area'],
        'bairros'            => ['area_geo',     'ST_Area'],
        'loteamentos'        => ['area_geo',     'ST_Area'],
        'quadras'            => ['area_geo',     'ST_Area'],
        'logradouros'        => ['extensao_geo', 'ST_Length'],
    ];

    public function handle(): int
    {
        $tenantSlug    = $this->option('tenant');
        $entidadeOpt   = $this->option('entidade');
        $force         = (bool) $this->option('force');

        // Validação de --entidade
        if ($entidadeOpt && !isset(self::ENTIDADES[$entidadeOpt])) {
            $this->error("Entidade desconhecida: {$entidadeOpt}");
            $this->line('Entidades válidas: ' . implode(', ', array_keys(self::ENTIDADES)));
            return self::FAILURE;
        }

        // Resolve tenant (opcional)
        $tenantId = null;
        if ($tenantSlug) {
            $tenant = Tenant::where('slug', $tenantSlug)->first();
            if (!$tenant) {
                $this->error("Tenant '{$tenantSlug}' não encontrado.");
                return self::FAILURE;
            }
            $tenantId = $tenant->id;
            $this->info("Tenant: {$tenant->name} (#{$tenant->id})");
        } else {
            $this->info('Tenant: todos');
        }

        $alvos = $entidadeOpt
            ? [$entidadeOpt => self::ENTIDADES[$entidadeOpt]]
            : self::ENTIDADES;

        $this->newLine();
        $this->line($force
            ? '🔁 Modo --force: recalcula TODOS os registros (sobrescreve valores).'
            : '✅ Modo idempotente: só recalcula linhas com coluna NULL.');
        $this->newLine();

        $totalGeral = 0;

        foreach ($alvos as $tabela => [$coluna, $fn]) {
            $sql = "UPDATE {$tabela}
                    SET {$coluna} = {$fn}(geo::geography)
                    WHERE geo IS NOT NULL";

            if (!$force) {
                $sql .= " AND {$coluna} IS NULL";
            }

            $bindings = [];
            if ($tenantId !== null) {
                $sql .= ' AND tenant_id = ?';
                $bindings[] = $tenantId;
            }

            try {
                $affected = DB::update($sql, $bindings);
                $totalGeral += $affected;
                $this->line(sprintf(
                    '  %s → %s: %d linha(s) atualizadas',
                    str_pad($tabela, 22),
                    $coluna,
                    $affected
                ));
            } catch (\Throwable $e) {
                $this->error("  {$tabela}: ERRO — " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Total atualizado: {$totalGeral} linha(s).");

        return self::SUCCESS;
    }
}
