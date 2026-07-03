<?php

namespace App\Policies;

use App\Models\FluxoChamado;
use App\Models\User;

class FluxoChamadoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_fluxos_chamado');
    }

    public function view(User $user, FluxoChamado $model): bool
    {
        return $user->hasPermissionTo('gerenciar_fluxos_chamado');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_fluxos_chamado');
    }

    public function update(User $user, FluxoChamado $model): bool
    {
        return $user->hasPermissionTo('gerenciar_fluxos_chamado');
    }

    public function delete(User $user, FluxoChamado $model): bool
    {
        return $user->hasPermissionTo('gerenciar_fluxos_chamado');
    }
}
