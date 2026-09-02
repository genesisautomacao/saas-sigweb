<?php

namespace App\Policies;

use App\Models\{MobPontoInteresse, User};

class MobPontoInteressePolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('gerenciar_mob_pontos_interesse'); }
    public function view(User $user, MobPontoInteresse $model): bool { return $user->temPermissao('gerenciar_mob_pontos_interesse'); }
    public function create(User $user): bool { return $user->temPermissao('gerenciar_mob_pontos_interesse'); }
    public function update(User $user, MobPontoInteresse $model): bool { return $user->temPermissao('gerenciar_mob_pontos_interesse'); }
    public function delete(User $user, MobPontoInteresse $model): bool { return $user->temPermissao('gerenciar_mob_pontos_interesse'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('gerenciar_mob_pontos_interesse'); }
    public function restore(User $user, MobPontoInteresse $model): bool { return $user->temPermissao('gerenciar_mob_pontos_interesse'); }
    public function forceDelete(User $user, MobPontoInteresse $model): bool { return $user->temPermissao('gerenciar_mob_pontos_interesse'); }
}
