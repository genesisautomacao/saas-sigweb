<?php

namespace App\Policies;

use App\Models\Cemiterio;
use App\Models\User;

class CemiterioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_cemiterios');
    }
    
    public function view(User $user, Cemiterio $cemiterio): bool
    {
        return $user->hasPermissionTo('view_cemiterios');
    }
    
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_cemiterios');
    }
    
    public function update(User $user, Cemiterio $cemiterio): bool
    {
        return $user->hasPermissionTo('edit_cemiterios');
    }
    
    public function delete(User $user, Cemiterio $cemiterio): bool
    {
        return $user->hasPermissionTo('delete_cemiterios');
    }
}