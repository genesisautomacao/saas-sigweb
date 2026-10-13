<?php

namespace App\Console\Commands;

use App\Services\Permissoes\MigracaoPermissoesMapaR83;
use Illuminate\Console\Command;

/**
 * Release 83 — mostra o que a migration `2026_10_12_100000_r83_permissoes_mapa_por_acao`
 * vai criar, conceder a cada papel e apagar. Só simula: quem aplica é `php artisan migrate`.
 * Depois de aplicada, imprime "nada a fazer".
 */
class PermissoesSimularR83 extends Command
{
    protected $signature = 'permissoes:simular-r83';

    protected $description = 'Simula a conversão de permissões do mapa da Release 83 (gerenciar_/toolbar_ → quarteto + ferramenta_*), papel a papel. Não grava.';

    public function handle(MigracaoPermissoesMapaR83 $migracao): int
    {
        $plano = $migracao->plano();

        if ($plano['novas'] === [] && $plano['apagar'] === [] && $plano['papeis'] === []) {
            $this->info('Nada a fazer — a Release 83 já está aplicada neste banco.');

            return self::SUCCESS;
        }

        $this->line('Modo SIMULAÇÃO: nada será gravado (aplique com `php artisan migrate`).');
        $this->newLine();

        $this->info(sprintf('Permissões a criar (%d):', count($plano['novas'])));
        $this->line('  '.(implode(', ', $plano['novas']) ?: '—'));
        $this->newLine();

        $this->info(sprintf('Permissões a apagar (%d):', count($plano['apagar'])));
        $this->line('  '.(implode(', ', $plano['apagar']) ?: '—'));
        $this->newLine();

        $this->info(sprintf('Papéis que recebem permissões (%d):', count($plano['papeis'])));
        $tenantAtual = null;
        foreach ($plano['papeis'] as $papel) {
            if ($papel['tenant'] !== $tenantAtual) {
                $tenantAtual = $papel['tenant'];
                $this->line('  '.$tenantAtual);
            }
            $this->line(sprintf('    %-30s + %s', $papel['nome'], implode(', ', $papel['adicionar'])));
        }
        if ($plano['papeis'] === []) {
            $this->line('  —');
        }

        $this->newLine();
        $this->line('Regra: papel com ver_camada_X + toolbar_criar_artefatos ganha criar/editar/excluir de X; quem só via fica só leitura;');
        $this->line('gerenciar_X vira o quarteto; toolbar_ferramentas vira as ferramenta_* do menu; toolbar_filtros vira filtros+estatísticas;');
        $this->line('cores e street_view para todos; enquadramento para ninguém (Master/Manager têm bypass).');

        return self::SUCCESS;
    }
}
