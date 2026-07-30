<?php

namespace App\Policies;

use App\Models\ColetaAtribuicao;
use App\Models\User;

class ColetaAtribuicaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_atribuicoes_coleta');
    }

    public function view(User $user, ColetaAtribuicao $model): bool
    {
        return $user->hasPermissionTo('gerenciar_atribuicoes_coleta');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_atribuicoes_coleta');
    }

    public function update(User $user, ColetaAtribuicao $model): bool
    {
        return $user->hasPermissionTo('gerenciar_atribuicoes_coleta');
    }

    public function delete(User $user, ColetaAtribuicao $model): bool
    {
        return $user->hasPermissionTo('gerenciar_atribuicoes_coleta');
    }
}
