<?php

namespace App\Policies;

use App\Models\{TipoEntidade, User};

class TipoEntidadePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_tipo_entidades'); }
    public function view(User $user, TipoEntidade $model): bool { return $user->hasPermissionTo('gerenciar_tipo_entidades'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_tipo_entidades'); }
    public function update(User $user, TipoEntidade $model): bool { return $user->hasPermissionTo('gerenciar_tipo_entidades'); }
    public function delete(User $user, TipoEntidade $model): bool { return $user->hasPermissionTo('gerenciar_tipo_entidades'); }
}
