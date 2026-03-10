<?php

namespace App\Policies;

use App\Models\Jazigo;
use App\Models\User;

class JazigoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_jazigos');
    }
    
    public function view(User $user, Jazigo $jazigos): bool
    {
        return $user->hasPermissionTo('view_jazigos');
    }
    
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_jazigos');
    }
    
    public function update(User $user, Jazigo $jazigos): bool
    {
        return $user->hasPermissionTo('edit_jazigos');
    }
    
    public function delete(User $user, Jazigo $jazigos): bool
    {
        return $user->hasPermissionTo('delete_jazigos');
    }
}