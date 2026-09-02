<?php

namespace App\Policies;

use App\Models\{MobSinalizacao, User};

class MobSinalizacaoPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('gerenciar_mob_sinalizacoes'); }
    public function view(User $user, MobSinalizacao $model): bool { return $user->temPermissao('gerenciar_mob_sinalizacoes'); }
    public function create(User $user): bool { return $user->temPermissao('gerenciar_mob_sinalizacoes'); }
    public function update(User $user, MobSinalizacao $model): bool { return $user->temPermissao('gerenciar_mob_sinalizacoes'); }
    public function delete(User $user, MobSinalizacao $model): bool { return $user->temPermissao('gerenciar_mob_sinalizacoes'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('gerenciar_mob_sinalizacoes'); }
    public function restore(User $user, MobSinalizacao $model): bool { return $user->temPermissao('gerenciar_mob_sinalizacoes'); }
    public function forceDelete(User $user, MobSinalizacao $model): bool { return $user->temPermissao('gerenciar_mob_sinalizacoes'); }
}
