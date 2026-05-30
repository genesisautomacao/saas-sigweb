<?php

namespace App\Policies;

use App\Models\MeioFio;
use App\Models\User;

class MeioFioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_meio_fios');
    }

    public function view(User $user, MeioFio $meioFio): bool
    {
        return $user->hasPermissionTo('view_meio_fios');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_meio_fios');
    }

    public function update(User $user, MeioFio $meioFio): bool
    {
        return $user->hasPermissionTo('edit_meio_fios');
    }

    public function delete(User $user, MeioFio $meioFio): bool
    {
        return $user->hasPermissionTo('delete_meio_fios');
    }
}
