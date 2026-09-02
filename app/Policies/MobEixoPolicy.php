<?php

namespace App\Policies;

use App\Models\{MobEixo, User};

class MobEixoPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('gerenciar_mob_eixos'); }
    public function view(User $user, MobEixo $model): bool { return $user->temPermissao('gerenciar_mob_eixos'); }
    public function create(User $user): bool { return $user->temPermissao('gerenciar_mob_eixos'); }
    public function update(User $user, MobEixo $model): bool { return $user->temPermissao('gerenciar_mob_eixos'); }
    public function delete(User $user, MobEixo $model): bool { return $user->temPermissao('gerenciar_mob_eixos'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('gerenciar_mob_eixos'); }
    public function restore(User $user, MobEixo $model): bool { return $user->temPermissao('gerenciar_mob_eixos'); }
    public function forceDelete(User $user, MobEixo $model): bool { return $user->temPermissao('gerenciar_mob_eixos'); }
}
