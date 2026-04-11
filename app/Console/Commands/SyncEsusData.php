<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Services\ApiTools\EsusSyncService;
use Illuminate\Support\Facades\Log;

class SyncEsusData extends Command
{
    /**
     * O nome do comando que você digitará no terminal
     */
    protected $signature = 'sigweb:sync-esus';

    /**
     * A descrição do comando
     */
    protected $description = 'Executa o ETL diário consumindo os dados do e-SUS AB para todas as prefeituras ativas.';

    /**
     * Executa o comando
     */
    public function handle(EsusSyncService $syncService)
    {
        $this->info('🚀 Iniciando sincronização noturna do e-SUS...');
        Log::info('CRON JOB: Iniciando sigweb:sync-esus');

        // Busca apenas as prefeituras (Tenants) que estão ativas no sistema
        $tenants = Tenant::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhuma prefeitura ativa encontrada para sincronizar.');
            return;
        }

        // Barra de progresso bonita no terminal
        $this->withProgressBar($tenants, function (Tenant $tenant) use ($syncService) {
            $this->newLine();
            $this->line("📡 Processando: {$tenant->name}");
            
            $sucesso = $syncService->syncTenant($tenant);

            if ($sucesso) {
                $this->info("✅ [{$tenant->name}] Sincronizado com sucesso!");
            } else {
                $this->error("❌ [{$tenant->name}] Falha ou ignorado (Consulte os logs de erro).");
            }
        });

        $this->newLine(2);
        $this->info('🏁 Sincronização concluída com sucesso!');
        Log::info('CRON JOB: Sincronização sigweb:sync-esus finalizada.');
    }
}