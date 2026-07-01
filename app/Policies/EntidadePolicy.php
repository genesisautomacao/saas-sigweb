<?php

namespace App\Policies;

use App\Models\{Entidade, User};

class EntidadePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_entidades'); }
    public function view(User $user, Entidade $model): bool { return $user->hasPermissionTo('gerenciar_entidades'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_entidades'); }
    public function update(User $user, Entidade $model): bool { return $user->hasPermissionTo('gerenciar_entidades'); }
    public function delete(User $user, Entidade $model): bool { return $user->hasPermissionTo('gerenciar_entidades'); }
}
