<?php

namespace App\Policies;

use App\Models\MobTrecho;
use App\Models\User;

/** R83 (2026-10-12): quarteto no lugar de gerenciar_mob_trechos. temPermissao = falha fechada (incidente 2026-08-05). */
class MobTrechoPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('view_mob_trechos'); }
    public function view(User $user, MobTrecho $model): bool { return $user->temPermissao('view_mob_trechos'); }
    public function create(User $user): bool { return $user->temPermissao('create_mob_trechos'); }
    public function update(User $user, MobTrecho $model): bool { return $user->temPermissao('edit_mob_trechos'); }
    public function delete(User $user, MobTrecho $model): bool { return $user->temPermissao('delete_mob_trechos'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('delete_mob_trechos'); }
    public function restore(User $user, MobTrecho $model): bool { return $user->temPermissao('delete_mob_trechos'); }
    public function forceDelete(User $user, MobTrecho $model): bool { return $user->temPermissao('delete_mob_trechos'); }
}
