<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Modulos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill das chaves de módulo criadas em 2026-09-04 (docs/Modulos_Permissoes.txt):
 *  - base_cartografica: toda prefeitura com imobiliario (é pré-requisito dele);
 *  - coleta_cadastral: toda prefeitura com imobiliario (todas as atuais usam a
 *    coleta/chamados; desligar depois no /admin é uma decisão comercial);
 *  - imageamento: prefeitura que já tem pontos panorâmicos cadastrados;
 *  - chamados (D8, 2026-09-05): toda prefeitura com coleta_cadastral — o App de
 *    Chamados fazia parte da coleta e virou módulo próprio; ninguém perde acesso.
 * Idempotente. Salvar `modules` dispara o Tenant::updated → papéis "todos os
 * módulos" (Manager) recebem as permissões novas.
 *
 *   php artisan modulos:backfill            (simulação)
 *   php artisan modulos:backfill --executar
 */
class ModulosBackfill extends Command
{
    protected $signature = 'modulos:backfill {--executar : Grava as alterações (sem a flag só simula)}';

    protected $description = 'Adiciona às prefeituras as chaves de módulo novas (base_cartografica, coleta_cadastral, imageamento, chamados) conforme o que já usam.';

    public function handle(): int
    {
        $executar = (bool) $this->option('executar');
        $this->line($executar ? 'Modo EXECUÇÃO: gravando.' : 'Modo SIMULAÇÃO: nada será gravado (use --executar).');
        $this->newLine();

        $alterados = 0;
        foreach (Tenant::orderBy('name')->get() as $tenant) {
            $atuais = array_values(array_unique((array) ($tenant->modules ?? [])));
            $novos = $atuais;

            if (in_array('imobiliario', $atuais, true)) {
                $novos[] = 'base_cartografica';
                $novos[] = 'coleta_cadastral';
            }
            if (DB::table('pontos_panoramicos')->where('tenant_id', $tenant->id)->whereNull('deleted_at')->exists()) {
                $novos[] = 'imageamento';
            }
            // D8: quem tinha a coleta (que incluía os chamados) continua vendo o App de Chamados.
            if (in_array('coleta_cadastral', $novos, true)) {
                $novos[] = 'chamados';
            }

            $novos = array_values(array_unique($novos));
            $adicionados = array_values(array_diff($novos, $atuais));
            $desconhecidos = array_values(array_diff($novos, Modulos::chaves()));

            if ($adicionados === [] && $desconhecidos === []) {
                $this->line(sprintf('  %-40s ok (%s)', $tenant->slug, implode(', ', $atuais) ?: 'sem módulos'));

                continue;
            }

            $this->line(sprintf('  %-40s + %s%s', $tenant->slug, implode(', ', $adicionados) ?: '—',
                $desconhecidos ? '  ⚠ chaves desconhecidas no catálogo: '.implode(', ', $desconhecidos) : ''));

            if ($executar && $adicionados !== []) {
                $tenant->modules = $novos;
                $tenant->save(); // dispara Tenant::updated → sincronizarPapeisTodosModulos()
                $alterados++;
            }
        }

        $this->newLine();
        $this->info($executar ? "Concluído: {$alterados} prefeitura(s) atualizada(s)." : 'Simulação concluída.');

        return self::SUCCESS;
    }
}
