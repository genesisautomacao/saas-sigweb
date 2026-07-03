<?php

namespace App\Policies;

use App\Models\Chamado;
use App\Models\User;

class ChamadoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_chamados');
    }

    public function view(User $user, Chamado $model): bool
    {
        return $user->hasPermissionTo('gerenciar_chamados');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_chamados');
    }

    public function update(User $user, Chamado $model): bool
    {
        return $user->hasPermissionTo('gerenciar_chamados');
    }

    public function delete(User $user, Chamado $model): bool
    {
        return $user->hasPermissionTo('gerenciar_chamados');
    }
}
