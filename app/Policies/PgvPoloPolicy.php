<?php

namespace App\Policies;

use App\Models\{PgvPolo, User};

class PgvPoloPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_pgv_polos'); }
    public function view(User $user, PgvPolo $model): bool { return $user->hasPermissionTo('gerenciar_pgv_polos'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_pgv_polos'); }
    public function update(User $user, PgvPolo $model): bool { return $user->hasPermissionTo('gerenciar_pgv_polos'); }
    public function delete(User $user, PgvPolo $model): bool { return $user->hasPermissionTo('gerenciar_pgv_polos'); }
}
