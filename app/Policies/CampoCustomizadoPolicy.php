<?php

namespace App\Policies;

use App\Models\CampoCustomizado;
use App\Models\User;

class CampoCustomizadoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_campos_customizados');
    }

    public function view(User $user, CampoCustomizado $model): bool
    {
        return $user->hasPermissionTo('gerenciar_campos_customizados');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_campos_customizados');
    }

    public function update(User $user, CampoCustomizado $model): bool
    {
        return $user->hasPermissionTo('gerenciar_campos_customizados');
    }

    public function delete(User $user, CampoCustomizado $model): bool
    {
        return $user->hasPermissionTo('gerenciar_campos_customizados');
    }
}
