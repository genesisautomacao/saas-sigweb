<?php

namespace App\Policies;

use App\Models\{MobFluxo, User};

class MobFluxoPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('gerenciar_mob_fluxos'); }
    public function view(User $user, MobFluxo $model): bool { return $user->temPermissao('gerenciar_mob_fluxos'); }
    public function create(User $user): bool { return $user->temPermissao('gerenciar_mob_fluxos'); }
    public function update(User $user, MobFluxo $model): bool { return $user->temPermissao('gerenciar_mob_fluxos'); }
    public function delete(User $user, MobFluxo $model): bool { return $user->temPermissao('gerenciar_mob_fluxos'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('gerenciar_mob_fluxos'); }
    public function restore(User $user, MobFluxo $model): bool { return $user->temPermissao('gerenciar_mob_fluxos'); }
    public function forceDelete(User $user, MobFluxo $model): bool { return $user->temPermissao('gerenciar_mob_fluxos'); }
}
