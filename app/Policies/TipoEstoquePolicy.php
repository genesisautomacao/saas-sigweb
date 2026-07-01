<?php

namespace App\Policies;

use App\Models\{TipoEstoque, User};

class TipoEstoquePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_tipo_estoques'); }
    public function view(User $user, TipoEstoque $model): bool { return $user->hasPermissionTo('gerenciar_tipo_estoques'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_tipo_estoques'); }
    public function update(User $user, TipoEstoque $model): bool { return $user->hasPermissionTo('gerenciar_tipo_estoques'); }
    public function delete(User $user, TipoEstoque $model): bool { return $user->hasPermissionTo('gerenciar_tipo_estoques'); }
}
