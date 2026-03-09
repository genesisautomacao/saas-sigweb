<?php

namespace App\Policies;

use App\Models\Poste;
use App\Models\User;

class PostePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_postes');
    }
    public function view(User $user, Poste $poste): bool
    {
        return $user->hasPermissionTo('view_postes');
    }
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_postes');
    }
    public function update(User $user, Poste $poste): bool
    {
        return $user->hasPermissionTo('edit_postes');
    }
    public function delete(User $user, Poste $poste): bool
    {
        return $user->hasPermissionTo('delete_postes');
    }
}