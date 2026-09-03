<?php

namespace App\Policies;

use App\Models\MobCamera;
use App\Models\User;

class MobCameraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->temPermissao('gerenciar_mob_cameras');
    }

    public function view(User $user, MobCamera $model): bool
    {
        return $user->temPermissao('gerenciar_mob_cameras');
    }

    public function create(User $user): bool
    {
        return $user->temPermissao('gerenciar_mob_cameras');
    }

    public function update(User $user, MobCamera $model): bool
    {
        return $user->temPermissao('gerenciar_mob_cameras');
    }

    public function delete(User $user, MobCamera $model): bool
    {
        return $user->temPermissao('gerenciar_mob_cameras');
    }

    public function deleteAny(User $user): bool
    {
        return $user->temPermissao('gerenciar_mob_cameras');
    }

    public function restore(User $user, MobCamera $model): bool
    {
        return $user->temPermissao('gerenciar_mob_cameras');
    }

    public function forceDelete(User $user, MobCamera $model): bool
    {
        return $user->temPermissao('gerenciar_mob_cameras');
    }
}
