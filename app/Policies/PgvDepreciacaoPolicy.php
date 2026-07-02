<?php

namespace App\Policies;

use App\Models\{PgvDepreciacao, User};

class PgvDepreciacaoPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_pgv_depreciacoes'); }
    public function view(User $user, PgvDepreciacao $model): bool { return $user->hasPermissionTo('gerenciar_pgv_depreciacoes'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_pgv_depreciacoes'); }
    public function update(User $user, PgvDepreciacao $model): bool { return $user->hasPermissionTo('gerenciar_pgv_depreciacoes'); }
    public function delete(User $user, PgvDepreciacao $model): bool { return $user->hasPermissionTo('gerenciar_pgv_depreciacoes'); }
}
