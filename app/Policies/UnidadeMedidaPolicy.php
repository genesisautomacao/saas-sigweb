<?php

namespace App\Policies;

use App\Models\{UnidadeMedida, User};

class UnidadeMedidaPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_unidade_medidas'); }
    public function view(User $user, UnidadeMedida $model): bool { return $user->hasPermissionTo('gerenciar_unidade_medidas'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_unidade_medidas'); }
    public function update(User $user, UnidadeMedida $model): bool { return $user->hasPermissionTo('gerenciar_unidade_medidas'); }
    public function delete(User $user, UnidadeMedida $model): bool { return $user->hasPermissionTo('gerenciar_unidade_medidas'); }
}
