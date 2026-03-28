<?php

namespace App\Policies;

use App\Models\TipoPatrimonio;
use App\Models\User;

class TipoPatrimonioPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_tipo_patrimonios'); }
    public function view(User $user, TipoPatrimonio $model): bool { return $user->hasPermissionTo('view_tipo_patrimonios'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_tipo_patrimonios'); }
    public function update(User $user, TipoPatrimonio $model): bool { return $user->hasPermissionTo('edit_tipo_patrimonios'); }
    public function delete(User $user, TipoPatrimonio $model): bool { return $user->hasPermissionTo('delete_tipo_patrimonios'); }
}