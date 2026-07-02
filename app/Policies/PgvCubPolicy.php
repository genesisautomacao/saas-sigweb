<?php

namespace App\Policies;

use App\Models\{PgvCub, User};

class PgvCubPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_pgv_cubs'); }
    public function view(User $user, PgvCub $model): bool { return $user->hasPermissionTo('gerenciar_pgv_cubs'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_pgv_cubs'); }
    public function update(User $user, PgvCub $model): bool { return $user->hasPermissionTo('gerenciar_pgv_cubs'); }
    public function delete(User $user, PgvCub $model): bool { return $user->hasPermissionTo('gerenciar_pgv_cubs'); }
}
