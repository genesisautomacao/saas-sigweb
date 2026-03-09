<?php

namespace App\Policies;

use App\Models\Endereco;
use App\Models\User;

class EnderecoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_enderecos');
    }

    public function view(User $user, Endereco $endereco): bool
    {
        return $user->hasPermissionTo('view_enderecos');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_enderecos');
    }

    public function update(User $user, Endereco $endereco): bool
    {
        return $user->hasPermissionTo('edit_enderecos');
    }

    public function delete(User $user, Endereco $endereco): bool
    {
        return $user->hasPermissionTo('delete_enderecos');
    }
}