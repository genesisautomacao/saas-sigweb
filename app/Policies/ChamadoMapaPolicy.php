<?php

namespace App\Policies;

use App\Models\ChamadoMapa;
use App\Models\User;

/**
 * R80-1 — Chamados pelo mapa público. Permissão única "gerenciar" (falha fechada via
 * temPermissao — deploy sem o PermissionsSeeder nega em vez de derrubar o menu).
 * O cidadão cria pelo mapa público (sem login); no painel ninguém cria chamado.
 */
class ChamadoMapaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->temPermissao('gerenciar_chamados_mapa');
    }

    public function view(User $user, ChamadoMapa $chamado): bool
    {
        return $user->temPermissao('gerenciar_chamados_mapa');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ChamadoMapa $chamado): bool
    {
        return $user->temPermissao('gerenciar_chamados_mapa');
    }

    public function delete(User $user, ChamadoMapa $chamado): bool
    {
        return $user->temPermissao('gerenciar_chamados_mapa');
    }

    public function deleteAny(User $user): bool
    {
        return $user->temPermissao('gerenciar_chamados_mapa');
    }
}
