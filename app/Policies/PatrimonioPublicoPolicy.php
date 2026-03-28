<?php

namespace App\Policies;

use App\Models\PatrimonioPublico;
use App\Models\User;

class PatrimonioPublicoPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_patrimonio_publicos'); }
    public function view(User $user, PatrimonioPublico $model): bool { return $user->hasPermissionTo('view_patrimonio_publicos'); }
    public function create(User $user): bool { return $user->hasPermissionTo('create_patrimonio_publicos'); }
    public function update(User $user, PatrimonioPublico $model): bool { return $user->hasPermissionTo('edit_patrimonio_publicos'); }
    public function delete(User $user, PatrimonioPublico $model): bool { return $user->hasPermissionTo('delete_patrimonio_publicos'); }
}