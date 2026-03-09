<?php

namespace App\Policies;

use App\Models\TipoPoste;
use App\Models\User;

class TipoPostePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_tipos_poste');
    }
    public function view(User $user, TipoPoste $poste): bool
    {
        return $user->hasPermissionTo('view_tipos_poste');
    }
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_tipos_poste');
    }
    public function update(User $user, TipoPoste $poste): bool
    {
        return $user->hasPermissionTo('edit_tipos_poste');
    }
    public function delete(User $user, TipoPoste $poste): bool
    {
        return $user->hasPermissionTo('delete_postes');
    }
}