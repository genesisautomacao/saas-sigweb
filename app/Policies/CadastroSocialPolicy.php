<?php

namespace App\Policies;

use App\Models\CadastroSocial;
use App\Models\User;

class CadastroSocialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_cadastros_sociais');
    }

    public function view(User $user, CadastroSocial $cadastroSocial): bool
    {
        return $user->hasPermissionTo('view_cadastros_sociais');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_cadastros_sociais');
    }

    public function update(User $user, CadastroSocial $cadastroSocial): bool
    {
        return $user->hasPermissionTo('edit_cadastros_sociais');
    }

    public function delete(User $user, CadastroSocial $cadastroSocial): bool
    {
        return $user->hasPermissionTo('delete_cadastros_sociais');
    }

    public function restore(User $user, CadastroSocial $cadastroSocial): bool
    {
        return $user->hasPermissionTo('delete_cadastros_sociais'); // Usa a mesma flag de delete
    }

    public function forceDelete(User $user, CadastroSocial $cadastroSocial): bool
    {
        return $user->hasPermissionTo('delete_cadastros_sociais');
    }
}