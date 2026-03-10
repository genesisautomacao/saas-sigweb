<?php

namespace App\Policies;

use App\Models\LogradouroCemiterio;
use App\Models\User;

class LogradouroCemiterioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_logradouros_cemiterio');
    }
    
    public function view(User $user, LogradouroCemiterio $logradouros_cemiterio): bool
    {
        return $user->hasPermissionTo('view_logradouros_cemiterio');
    }
    
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_logradouros_cemiterio');
    }
    
    public function update(User $user, LogradouroCemiterio $logradouros_cemiterio): bool
    {
        return $user->hasPermissionTo('edit_logradouros_cemiterio');
    }
    
    public function delete(User $user, LogradouroCemiterio $logradouros_cemiterio): bool
    {
        return $user->hasPermissionTo('delete_logradouros_cemiterio');
    }
}