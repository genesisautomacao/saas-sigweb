<?php

namespace App\Policies;

use App\Models\CategoriaWms;
use App\Models\User;

class CategoriaWmsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_wms');
    }

    public function view(User $user, CategoriaWms $model): bool
    {
        return $user->hasPermissionTo('gerenciar_wms');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_wms');
    }

    public function update(User $user, CategoriaWms $model): bool
    {
        return $user->hasPermissionTo('gerenciar_wms');
    }

    public function delete(User $user, CategoriaWms $model): bool
    {
        return $user->hasPermissionTo('gerenciar_wms');
    }
}
