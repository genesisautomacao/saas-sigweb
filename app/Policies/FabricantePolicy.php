<?php

namespace App\Policies;

use App\Models\{Fabricante, User};

class FabricantePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_fabricantes'); }
    public function view(User $user, Fabricante $model): bool { return $user->hasPermissionTo('gerenciar_fabricantes'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_fabricantes'); }
    public function update(User $user, Fabricante $model): bool { return $user->hasPermissionTo('gerenciar_fabricantes'); }
    public function delete(User $user, Fabricante $model): bool { return $user->hasPermissionTo('gerenciar_fabricantes'); }
}
