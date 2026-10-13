<?php

namespace App\Policies;

use App\Models\MobEixo;
use App\Models\User;

/** R83 (2026-10-12): quarteto no lugar de gerenciar_mob_eixos. temPermissao = falha fechada (incidente 2026-08-05). */
class MobEixoPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('view_mob_eixos'); }
    public function view(User $user, MobEixo $model): bool { return $user->temPermissao('view_mob_eixos'); }
    public function create(User $user): bool { return $user->temPermissao('create_mob_eixos'); }
    public function update(User $user, MobEixo $model): bool { return $user->temPermissao('edit_mob_eixos'); }
    public function delete(User $user, MobEixo $model): bool { return $user->temPermissao('delete_mob_eixos'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('delete_mob_eixos'); }
    public function restore(User $user, MobEixo $model): bool { return $user->temPermissao('delete_mob_eixos'); }
    public function forceDelete(User $user, MobEixo $model): bool { return $user->temPermissao('delete_mob_eixos'); }
}
