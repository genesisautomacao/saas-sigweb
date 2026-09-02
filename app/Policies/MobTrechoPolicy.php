<?php

namespace App\Policies;

use App\Models\{MobTrecho, User};

/** Permissão única (docs/piuma.txt). temPermissao = falha fechada p/ permissão recém-criada (incidente 2026-08-05). */
class MobTrechoPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('gerenciar_mob_trechos'); }
    public function view(User $user, MobTrecho $model): bool { return $user->temPermissao('gerenciar_mob_trechos'); }
    public function create(User $user): bool { return $user->temPermissao('gerenciar_mob_trechos'); }
    public function update(User $user, MobTrecho $model): bool { return $user->temPermissao('gerenciar_mob_trechos'); }
    public function delete(User $user, MobTrecho $model): bool { return $user->temPermissao('gerenciar_mob_trechos'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('gerenciar_mob_trechos'); }
    public function restore(User $user, MobTrecho $model): bool { return $user->temPermissao('gerenciar_mob_trechos'); }
    public function forceDelete(User $user, MobTrecho $model): bool { return $user->temPermissao('gerenciar_mob_trechos'); }
}
