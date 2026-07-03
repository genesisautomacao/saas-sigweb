<?php

namespace App\Policies;

use App\Models\FonteWms;
use App\Models\User;

class FonteWmsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_wms');
    }

    public function view(User $user, FonteWms $model): bool
    {
        return $user->hasPermissionTo('gerenciar_wms');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('gerenciar_wms');
    }

    public function update(User $user, FonteWms $model): bool
    {
        return $user->hasPermissionTo('gerenciar_wms');
    }

    public function delete(User $user, FonteWms $model): bool
    {
        return $user->hasPermissionTo('gerenciar_wms');
    }
}
