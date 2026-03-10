<?php

namespace App\Policies;

use App\Models\QuadraCemiterio;
use App\Models\User;

class QuadraCemiterioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_quadras_cemiterio');
    }
    
    public function view(User $user, QuadraCemiterio $quadras_cemiterio): bool
    {
        return $user->hasPermissionTo('view_quadras_cemiterio');
    }
    
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_quadras_cemiterio');
    }
    
    public function update(User $user, QuadraCemiterio $quadras_cemiterio): bool
    {
        return $user->hasPermissionTo('edit_quadras_cemiterio');
    }
    
    public function delete(User $user, QuadraCemiterio $quadras_cemiterio): bool
    {
        return $user->hasPermissionTo('delete_quadras_cemiterio');
    }
}