<?php

namespace App\Policies;

use App\Models\{PgvAmostra, User};

class PgvAmostraPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_pgv_amostras'); }
    public function view(User $user, PgvAmostra $model): bool { return $user->hasPermissionTo('gerenciar_pgv_amostras'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_pgv_amostras'); }
    public function update(User $user, PgvAmostra $model): bool { return $user->hasPermissionTo('gerenciar_pgv_amostras'); }
    public function delete(User $user, PgvAmostra $model): bool { return $user->hasPermissionTo('gerenciar_pgv_amostras'); }
}
