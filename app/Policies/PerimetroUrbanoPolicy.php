<?php

namespace App\Policies;

use App\Models\PerimetroUrbano;
use App\Models\User;

class PerimetroUrbanoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_perimetros_urbanos');
    }

    public function view(User $user, PerimetroUrbano $perimetroUrbano): bool
    {
        return $user->hasPermissionTo('view_perimetros_urbanos');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_perimetros_urbanos');
    }

    public function update(User $user, PerimetroUrbano $perimetroUrbano): bool
    {
        return $user->hasPermissionTo('edit_perimetros_urbanos');
    }

    public function delete(User $user, PerimetroUrbano $perimetroUrbano): bool
    {
        return $user->hasPermissionTo('delete_perimetros_urbanos');
    }
}
