<?php

namespace App\Policies;

use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PessoaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_pessoas');
    }

    public function view(User $user, Pessoa $pessoa): bool
    {
        return $user->hasPermissionTo('view_pessoas');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_pessoas');
    }

    public function update(User $user, Pessoa $pessoa): bool
    {
        return $user->hasPermissionTo('edit_pessoas');
    }

    public function delete(User $user, Pessoa $pessoa): bool
    {
        return $user->hasPermissionTo('delete_pessoas');
    }
}