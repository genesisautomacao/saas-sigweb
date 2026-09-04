<?php

namespace App\Policies;

use App\Models\MobVia;
use App\Models\User;

/** Permissão única (docs/piuma.txt, Onda 6). temPermissao = falha fechada p/ permissão recém-criada (incidente 2026-08-05). */
class MobViaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->temPermissao('gerenciar_mob_vias');
    }

    public function view(User $user, MobVia $model): bool
    {
        return $user->temPermissao('gerenciar_mob_vias');
    }

    public function create(User $user): bool
    {
        return $user->temPermissao('gerenciar_mob_vias');
    }

    public function update(User $user, MobVia $model): bool
    {
        return $user->temPermissao('gerenciar_mob_vias');
    }

    public function delete(User $user, MobVia $model): bool
    {
        return $user->temPermissao('gerenciar_mob_vias');
    }

    public function deleteAny(User $user): bool
    {
        return $user->temPermissao('gerenciar_mob_vias');
    }

    public function restore(User $user, MobVia $model): bool
    {
        return $user->temPermissao('gerenciar_mob_vias');
    }

    public function forceDelete(User $user, MobVia $model): bool
    {
        return $user->temPermissao('gerenciar_mob_vias');
    }
}
