<?php

namespace App\Policies;

use App\Models\{FamiliaProduto, User};

class FamiliaProdutoPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_familia_produtos'); }
    public function view(User $user, FamiliaProduto $model): bool { return $user->hasPermissionTo('gerenciar_familia_produtos'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_familia_produtos'); }
    public function update(User $user, FamiliaProduto $model): bool { return $user->hasPermissionTo('gerenciar_familia_produtos'); }
    public function delete(User $user, FamiliaProduto $model): bool { return $user->hasPermissionTo('gerenciar_familia_produtos'); }
}
