<?php

namespace App\Policies;

use App\Models\ColetaAtribuicao;
use App\Models\User;

class ColetaAtribuicaoPolicy
{
    // temPermissao() (não hasPermissionTo): permissão não semeada no banco = nega,
    // sem lançar PermissionDoesNotExist (incidente de produção de 2026-08-05).
    public function viewAny(User $user): bool
    {
        return $user->temPermissao('gerenciar_atribuicoes_coleta');
    }

    public function view(User $user, ColetaAtribuicao $model): bool
    {
        return $user->temPermissao('gerenciar_atribuicoes_coleta');
    }

    public function create(User $user): bool
    {
        return $user->temPermissao('gerenciar_atribuicoes_coleta');
    }

    public function update(User $user, ColetaAtribuicao $model): bool
    {
        return $user->temPermissao('gerenciar_atribuicoes_coleta');
    }

    public function delete(User $user, ColetaAtribuicao $model): bool
    {
        return $user->temPermissao('gerenciar_atribuicoes_coleta');
    }
}
