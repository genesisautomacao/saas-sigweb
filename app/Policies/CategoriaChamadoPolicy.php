<?php

namespace App\Policies;

use App\Models\CategoriaChamado;
use App\Models\User;

class CategoriaChamadoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_categorias_chamado');
    }

    public function view(User $user, CategoriaChamado $model): bool
    {
        return $user->hasPermissionTo('gerenciar_categorias_chamado');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_categorias_chamado');
    }

    public function update(User $user, CategoriaChamado $model): bool
    {
        return $user->hasPermissionTo('gerenciar_categorias_chamado');
    }

    public function delete(User $user, CategoriaChamado $model): bool
    {
        return $user->hasPermissionTo('gerenciar_categorias_chamado');
    }
}
