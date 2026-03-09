<?php

namespace App\Policies;

use App\Models\Contato;
use App\Models\User;

class ContatoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_contatos');
    }

    public function view(User $user, Contato $contato): bool
    {
        return $user->hasPermissionTo('view_contatos');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_contatos');
    }

    public function update(User $user, Contato $contato): bool
    {
        return $user->hasPermissionTo('edit_contatos');
    }

    public function delete(User $user, Contato $contato): bool
    {
        return $user->hasPermissionTo('delete_contatos');
    }
}