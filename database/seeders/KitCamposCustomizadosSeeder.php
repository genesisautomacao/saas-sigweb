<?php

namespace Database\Seeders;

use App\Services\Coleta\KitCamposCustomizadosService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Aplica o KIT INICIAL de campos customizados a todos os tenants existentes.
 * Idempotente — só cria o que falta. Para tenants com dado legado migrado das
 * antigas colunas, as opções incorporam os valores reais encontrados.
 *
 * Tenants novos recebem o kit automaticamente (hook created no model Tenant).
 */
class KitCamposCustomizadosSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $criados = KitCamposCustomizadosService::aplicar((int) $tenantId, derivarOpcoes: true);
            $this->command?->info("Tenant {$tenantId}: {$criados} campo(s) do kit criados.");
        }
    }
}
