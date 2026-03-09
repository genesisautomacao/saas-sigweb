<?php

namespace App\Policies;

use App\Models\Arvore;
use App\Models\User;

class ArvorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_arvores');
    }
    
    public function view(User $user, Arvore $arvore): bool
    {
        return $user->hasPermissionTo('view_arvores');
    }
    
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_arvores');
    }
    
    public function update(User $user, Arvore $arvore): bool
    {
        return $user->hasPermissionTo('edit_arvores');
    }
    
    public function delete(User $user, Arvore $arvore): bool
    {
        return $user->hasPermissionTo('delete_arvores');
    }
}