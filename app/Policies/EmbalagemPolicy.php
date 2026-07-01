<?php

namespace App\Policies;

use App\Models\{Embalagem, User};

class EmbalagemPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_embalagens'); }
    public function view(User $user, Embalagem $model): bool { return $user->hasPermissionTo('gerenciar_embalagens'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_embalagens'); }
    public function update(User $user, Embalagem $model): bool { return $user->hasPermissionTo('gerenciar_embalagens'); }
    public function delete(User $user, Embalagem $model): bool { return $user->hasPermissionTo('gerenciar_embalagens'); }
}
