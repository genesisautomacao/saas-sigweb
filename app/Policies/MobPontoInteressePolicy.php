<?php

namespace App\Policies;

use App\Models\MobPontoInteresse;
use App\Models\User;

/** R83 (2026-10-12): quarteto no lugar de gerenciar_mob_pontos_interesse. temPermissao = falha fechada (incidente 2026-08-05). */
class MobPontoInteressePolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('view_mob_pontos_interesse'); }
    public function view(User $user, MobPontoInteresse $model): bool { return $user->temPermissao('view_mob_pontos_interesse'); }
    public function create(User $user): bool { return $user->temPermissao('create_mob_pontos_interesse'); }
    public function update(User $user, MobPontoInteresse $model): bool { return $user->temPermissao('edit_mob_pontos_interesse'); }
    public function delete(User $user, MobPontoInteresse $model): bool { return $user->temPermissao('delete_mob_pontos_interesse'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('delete_mob_pontos_interesse'); }
    public function restore(User $user, MobPontoInteresse $model): bool { return $user->temPermissao('delete_mob_pontos_interesse'); }
    public function forceDelete(User $user, MobPontoInteresse $model): bool { return $user->temPermissao('delete_mob_pontos_interesse'); }
}
