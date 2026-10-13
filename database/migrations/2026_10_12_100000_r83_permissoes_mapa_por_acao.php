<?php

use App\Services\Permissoes\MigracaoPermissoesMapaR83;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Release 83 — permissões do mapa por camada e por AÇÃO (docs/pendenciasTangara.md).
 *
 * Cria as permissões novas (quarteto das 12 entidades que eram gerenciar_X,
 * create/edit/delete_toponimias e ferramenta_* por item do menu), converte os papéis
 * das prefeituras pela regra "preservar quem já cria" (ver o service) e apaga as
 * antigas (gerenciar_X ×12 + toolbar_* ×3). Relatório no console e no laravel.log.
 * Simulação antes do deploy: `php artisan permissoes:simular-r83`.
 * Depois: `db:seed --class=PermissionsSeeder` + `permission:cache-reset`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $plano = app(MigracaoPermissoesMapaR83::class)->aplicar();

        echo sprintf("  R83: %d permissão(ões) criada(s), %d apagada(s), %d papel(is) atualizado(s)\n",
            count($plano['novas']), count($plano['apagar']), count($plano['papeis']));
        foreach ($plano['papeis'] as $papel) {
            echo sprintf("    %s / %s: + %s\n", $papel['tenant'], $papel['nome'], implode(', ', $papel['adicionar']));
        }

        Log::info('R83 — permissões do mapa por ação aplicadas', $plano);
    }

    public function down(): void
    {
        app(MigracaoPermissoesMapaR83::class)->reverter();
    }
};
