<?php

namespace App\Policies;

use App\Models\PontoPanoramico;
use App\Models\User;

class PontoPanoramicoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_pontos_panoramicos');
    }
    public function view(User $user, PontoPanoramico $pontoPanoramico): bool
    {
        return $user->hasPermissionTo('view_pontos_panoramicos');
    }
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_pontos_panoramicos');
    }
    public function update(User $user, PontoPanoramico $pontoPanoramico): bool
    {
        return $user->hasPermissionTo('edit_pontos_panoramicos');
    }
    public function delete(User $user, PontoPanoramico $pontoPanoramico): bool
    {
        return $user->hasPermissionTo('delete_pontos_panoramicos');
    }
}
