<?php

namespace App\Policies;

use App\Models\{Setor, User};

class SetorPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_setores'); }
    public function view(User $user, Setor $model): bool { return $user->hasPermissionTo('gerenciar_setores'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_setores'); }
    public function update(User $user, Setor $model): bool { return $user->hasPermissionTo('gerenciar_setores'); }
    public function delete(User $user, Setor $model): bool { return $user->hasPermissionTo('gerenciar_setores'); }
}
